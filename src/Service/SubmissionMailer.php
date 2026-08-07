<?php

namespace App\Service;

use App\Entity\Submission;
use App\Enum\SubmissionType;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sync notification mails for moderation (shared hosting: no Messenger worker).
 * Failures are logged and never block submit/approve.
 */
final class SubmissionMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
        #[Autowire('%env(default::ADMIN_NOTIFY_EMAIL)%')]
        private readonly string $adminNotifyEmail,
    ) {
    }

    public function notifyAdminNewSubmission(Submission $submission): void
    {
        $map = $submission->getMap();
        $to = $map->getNotifyEmail() ?: $this->adminNotifyEmail;
        if ($to === '') {
            return;
        }

        $typeLabel = $this->typeLabel($submission->getType());
        $location = $submission->getLocation();
        $inboxUrl = $map->getOwner() !== null
            ? $this->urlGenerator->generate('map_admin_inbox', ['mapSlug' => $map->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->urlGenerator->generate('admin_inbox', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->send(
            to: $to,
            subject: sprintf('[%s] Neue Meldung: %s (#%s)', $map->getName(), $typeLabel, $submission->getId() ?? '?'),
            template: 'email/admin_new_submission.txt.twig',
            context: [
                'submission' => $submission,
                'typeLabel' => $typeLabel,
                'locationLabel' => $location?->getDisplayLabel(),
                'inboxUrl' => $inboxUrl,
                'mapName' => $map->getName(),
            ],
        );
    }

    public function notifySubmitterApproved(Submission $submission): void
    {
        $email = $submission->getEmail();
        if ($email === null || trim($email) === '') {
            return;
        }

        if (!\in_array($submission->getType(), [SubmissionType::New, SubmissionType::Correction], true)) {
            return;
        }

        $isNew = $submission->getType() === SubmissionType::New;
        $mapUrl = $this->urlGenerator->generate('map_show', [
            'mapSlug' => $submission->getMap()->getSlug(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->send(
            to: $email,
            subject: $isNew
                ? 'Dein Markermap-Vorschlag wurde freigegeben'
                : 'Deine Markermap-Änderung wurde übernommen',
            template: 'email/submitter_approved.txt.twig',
            context: [
                'submission' => $submission,
                'isNew' => $isNew,
                'locationLabel' => $submission->getLocation()?->getDisplayLabel(),
                'mapUrl' => $mapUrl,
            ],
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(string $to, string $subject, string $template, array $context): void
    {
        $message = (new TemplatedEmail())
            ->from(Address::create($this->from))
            ->to($to)
            ->subject($subject)
            ->textTemplate($template)
            ->context($context);

        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Submission mail failed.', [
                'to' => $to,
                'subject' => $subject,
                'exception' => $e,
            ]);
        }
    }

    private function typeLabel(SubmissionType $type): string
    {
        return match ($type) {
            SubmissionType::New => 'Neuer Vorschlag',
            SubmissionType::Correction => 'Änderung',
            SubmissionType::StatusReport => 'Nicht mehr vorhanden',
            SubmissionType::Confirmation => 'Bestätigung',
        };
    }
}
