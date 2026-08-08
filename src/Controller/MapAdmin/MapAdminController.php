<?php

namespace App\Controller\MapAdmin;

use App\Entity\Map;
use App\Entity\Submission;
use App\Map\MapSlug;
use App\Repository\LocationRepository;
use App\Repository\SubmissionRepository;
use App\Security\MapAdminVoter;
use App\Service\LocationDeepLink;
use App\Service\LocationWorkflow;
use App\Service\MapRegistrationService;
use App\Service\QrCodeGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/maps/{mapSlug}/admin', requirements: ['mapSlug' => MapSlug::PATTERN])]
final class MapAdminController extends AbstractController
{
    #[Route('/login', name: 'map_admin_login', methods: ['GET', 'POST'])]
    public function login(Map $map, Request $request, MapRegistrationService $registration): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('map_admin_inbox', ['mapSlug' => $map->getSlug()]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('map_admin_login'.$map->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $email = (string) $request->request->get('email', '');
            $registration->requestMagicLogin($map, $email);
            $this->addFlash('success', 'Wenn die E-Mail zum Owner passt, erhältst du einen Login-Link (ca. 20 Min. gültig).');

            return $this->redirectToRoute('map_admin_login', ['mapSlug' => $map->getSlug()]);
        }

        return $this->render('map_admin/login.html.twig', [
            'map' => $map,
        ]);
    }

    #[Route('/logout', name: 'map_admin_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Logout is handled by the security firewall.');
    }

    #[Route('', name: 'map_admin_inbox', methods: ['GET'])]
    #[IsGranted(MapAdminVoter::ADMIN, 'map')]
    public function inbox(Map $map, SubmissionRepository $submissions): Response
    {
        return $this->render('map_admin/inbox.html.twig', [
            'map' => $map,
            'submissions' => $submissions->findOpenOrdered($map),
        ]);
    }

    #[Route('/submissions/{id}/approve', name: 'map_admin_submission_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(MapAdminVoter::ADMIN, 'map')]
    public function approve(Map $map, Submission $submission, Request $request, LocationWorkflow $workflow): Response
    {
        $this->assertSubmissionOnMap($map, $submission);

        if (!$this->isCsrfTokenValid('approve'.$submission->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $workflow->approve($submission);
            $this->addFlash('success', sprintf('Submission #%d freigegeben.', $submission->getId()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('map_admin_inbox', ['mapSlug' => $map->getSlug()]);
    }

    #[Route('/submissions/{id}/reject', name: 'map_admin_submission_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(MapAdminVoter::ADMIN, 'map')]
    public function reject(Map $map, Submission $submission, Request $request, LocationWorkflow $workflow): Response
    {
        $this->assertSubmissionOnMap($map, $submission);

        if (!$this->isCsrfTokenValid('reject'.$submission->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $workflow->reject($submission);
            $this->addFlash('success', sprintf('Submission #%d abgelehnt.', $submission->getId()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('map_admin_inbox', ['mapSlug' => $map->getSlug()]);
    }

    #[Route('/locations', name: 'map_admin_locations', methods: ['GET'])]
    #[IsGranted(MapAdminVoter::ADMIN, 'map')]
    public function locations(Map $map, LocationRepository $locations): Response
    {
        return $this->render('map_admin/locations.html.twig', [
            'map' => $map,
            'locations' => $locations->findModeratable($map),
        ]);
    }

    #[Route('/locations/{id}/remove', name: 'map_admin_location_remove', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(MapAdminVoter::ADMIN, 'map')]
    public function removeLocation(
        Map $map,
        int $id,
        Request $request,
        LocationRepository $locations,
        LocationWorkflow $workflow,
    ): Response {
        if (!$this->isCsrfTokenValid('remove'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $location = $locations->find($id);
        if ($location === null || $location->getMap()->getId() !== $map->getId()) {
            throw $this->createNotFoundException();
        }

        try {
            $workflow->adminSoftRemove($location);
            $this->addFlash('success', sprintf('Location #%d entfernt.', $id));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $redirect = (string) $request->request->get('redirect', 'map_admin_locations');

        return $this->redirectToRoute(
            $redirect === 'map_admin_inbox' ? 'map_admin_inbox' : 'map_admin_locations',
            ['mapSlug' => $map->getSlug()],
        );
    }

    #[Route('/locations/{id}/qr.svg', name: 'map_admin_location_qr', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(MapAdminVoter::ADMIN, 'map')]
    public function locationQr(
        Map $map,
        int $id,
        LocationRepository $locations,
        LocationDeepLink $deepLink,
        QrCodeGenerator $qrCodes,
    ): Response {
        $location = $locations->find($id);
        if ($location === null || $location->getMap()->getId() !== $map->getId()) {
            throw $this->createNotFoundException();
        }

        $svg = $qrCodes->svg($deepLink->absoluteUrl($location));
        $filename = sprintf('markermap-%s-%s.svg', $map->getSlug(), $location->getPublicId());

        return new Response($svg, Response::HTTP_OK, [
            'Content-Type' => 'image/svg+xml; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'Cache-Control' => 'no-store',
        ]);
    }

    private function assertSubmissionOnMap(Map $map, Submission $submission): void
    {
        if ($submission->getMap()->getId() !== $map->getId()) {
            throw $this->createNotFoundException();
        }
    }
}
