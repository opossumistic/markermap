<?php

namespace App\Controller;

use App\Repository\LocationRepository;
use App\Service\ClientRateLimit;
use App\Service\LocationWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ReportLocationController extends AbstractController
{
    #[Route('/melden/{id}', name: 'location_report_gone', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function __invoke(
        int $id,
        Request $request,
        LocationRepository $locations,
        LocationWorkflow $workflow,
        RateLimiterFactoryInterface $publicWriteLimiter,
        ClientRateLimit $rateLimit,
    ): Response {
        if (!$this->isCsrfTokenValid('report_gone', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$rateLimit->tryConsume($publicWriteLimiter, $request)) {
            $this->addFlash('error', 'Zu viele Anfragen — bitte in ein paar Minuten erneut versuchen.');

            return $this->redirectToRoute('home');
        }

        $location = $locations->find($id);
        if ($location === null || !$location->getStatus()->isVisibleOnMap()) {
            $this->addFlash('error', 'Eintrag nicht gefunden.');

            return $this->redirectToRoute('home');
        }

        // Pending boxes: reporting gone soft-removes via status report still marks disputed —
        // for pending, soft-remove is cleaner via rejection; here allow report on active/disputed/pending.
        $note = trim((string) $request->request->get('note', ''));
        $workflow->reportGone($location, null, $note !== '' ? $note : null);
        $this->addFlash('success', 'Danke für den Hinweis — wir prüfen das.');

        return $this->redirectToRoute('home');
    }
}
