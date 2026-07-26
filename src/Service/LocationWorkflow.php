<?php

namespace App\Service;

use App\Entity\Location;
use App\Entity\Submission;
use App\Enum\LocationStatus;
use App\Enum\SubmissionType;
use App\Repository\SubmissionRepository;
use Doctrine\ORM\EntityManagerInterface;

final class LocationWorkflow
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SubmissionRepository $submissionRepository,
        private readonly ReverseGeocoder $reverseGeocoder,
        private readonly SubmissionMailer $submissionMailer,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function submitNew(array $payload, ?string $email = null): Submission
    {
        $payload = $this->enrichPayloadFromGeocode($payload);

        // Visible on map immediately as pending (gray); public GeoJSON hides UGC until approve.
        // Image stays on the submission payload only — not on the Location until approve.
        $locationPayload = $payload;
        unset($locationPayload['image_path']);

        $location = new Location();
        $location->applyPayload($locationPayload);

        $submission = new Submission(SubmissionType::New, $payload, $location, $email);
        $this->entityManager->persist($location);
        $this->entityManager->persist($submission);
        $this->entityManager->flush();

        $this->submissionMailer->notifyAdminNewSubmission($submission);

        return $submission;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function submitCorrection(Location $location, array $payload, ?string $email = null): Submission
    {
        $submission = new Submission(SubmissionType::Correction, $payload, $location, $email);
        $this->entityManager->persist($submission);
        $this->entityManager->flush();

        $this->submissionMailer->notifyAdminNewSubmission($submission);

        return $submission;
    }

    public function reportGone(Location $location, ?string $email = null, ?string $note = null): Submission
    {
        $payload = array_filter([
            'action' => 'gone',
            'note' => $note,
        ], static fn ($v) => $v !== null && $v !== '');

        $submission = new Submission(SubmissionType::StatusReport, $payload, $location, $email);
        $location->markDisputed();

        $this->entityManager->persist($submission);
        $this->entityManager->flush();

        $this->submissionMailer->notifyAdminNewSubmission($submission);

        return $submission;
    }

    public function confirmExists(Location $location, ?string $email = null): Submission
    {
        $location->confirm();
        $submission = new Submission(
            SubmissionType::Confirmation,
            ['action' => 'exists'],
            $location,
            $email,
        );
        // Confirmations are audit trail; no moderation queue pressure.
        $submission->approve();

        $this->entityManager->persist($submission);
        $this->entityManager->flush();

        return $submission;
    }

    /**
     * Admin removes a live/pending pin from the map and closes related open submissions.
     */
    public function adminSoftRemove(Location $location): void
    {
        if ($location->getStatus() === LocationStatus::Removed) {
            throw new \DomainException('Location is already removed.');
        }

        $location->softRemove();

        foreach ($this->submissionRepository->findOpenForLocation($location) as $submission) {
            $submission->reject();
        }

        $this->entityManager->flush();
    }

    public function approve(Submission $submission): void
    {
        if (!$submission->isOpen()) {
            throw new \DomainException('Submission is not open.');
        }

        match ($submission->getType()) {
            SubmissionType::New => $this->approveNew($submission),
            SubmissionType::Correction => $this->approveCorrection($submission),
            SubmissionType::StatusReport => $this->approveStatusReport($submission),
            SubmissionType::Confirmation => throw new \DomainException('Confirmations are auto-approved.'),
        };

        $submission->approve();
        $this->entityManager->flush();

        $this->submissionMailer->notifySubmitterApproved($submission);
    }

    public function reject(Submission $submission): void
    {
        if (!$submission->isOpen()) {
            throw new \DomainException('Submission is not open.');
        }

        if ($submission->getType() === SubmissionType::StatusReport) {
            $location = $submission->getLocation();
            $submission->reject();
            $this->entityManager->flush();

            if ($location !== null
                && $location->getStatus() === LocationStatus::Disputed
                && $this->submissionRepository->countOpenStatusReportsFor($location) === 0
            ) {
                $location->restoreFromDispute();
                $this->entityManager->flush();
            }

            return;
        }

        if ($submission->getType() === SubmissionType::New) {
            $location = $submission->getLocation();
            if ($location !== null && $location->getStatus() === LocationStatus::Pending) {
                $location->softRemove();
            }
        }

        $submission->reject();
        $this->entityManager->flush();
    }

    private function approveNew(Submission $submission): void
    {
        $location = $submission->getLocation();
        if ($location === null) {
            $location = Location::fromNewPayload($submission->getPayload());
            $this->entityManager->persist($location);
            $submission->setLocation($location);

            return;
        }

        $location->applyPayload($submission->getPayload());
        $location->activate();
    }

    private function approveCorrection(Submission $submission): void
    {
        $location = $submission->getLocation();
        if ($location === null) {
            throw new \DomainException('Correction submission requires a location.');
        }

        $payload = $submission->getPayload();
        unset($payload['reason']); // moderation hint only — never published on Location
        $location->applyPayload($payload);
    }

    private function approveStatusReport(Submission $submission): void
    {
        $location = $submission->getLocation();
        if ($location === null) {
            throw new \DomainException('Status report requires a location.');
        }

        $action = $submission->getPayload()['action'] ?? 'gone';
        if ($action === 'gone') {
            $location->softRemove();
        }
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function enrichPayloadFromGeocode(array $payload): array
    {
        if (!isset($payload['lat'], $payload['lng']) || !is_numeric($payload['lat']) || !is_numeric($payload['lng'])) {
            return $payload;
        }

        $geo = $this->reverseGeocoder->reverse((float) $payload['lat'], (float) $payload['lng']);
        if ($geo === null) {
            return $payload;
        }

        // Map-facing address comes from Nominatim when available (not free-text UGC).
        // Keep the submitter's typed street for admin if it differs.
        if ($geo['district'] !== null) {
            $payload['district'] = $geo['district'];
        }
        if ($geo['street'] !== null) {
            $submittedStreet = isset($payload['street']) ? trim((string) $payload['street']) : '';
            if ($submittedStreet !== '' && $submittedStreet !== $geo['street']) {
                $payload['street_submitted'] = $submittedStreet;
            }
            $payload['street'] = $geo['street'];
        }
        if ($geo['postalCode'] !== null) {
            $payload['postal_code'] = $geo['postalCode'];
        }

        return $payload;
    }
}
