<?php

namespace App\Controller;

use App\Entity\Map;
use App\Map\MapSlug;
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
    #[Route(
        '/maps/{mapSlug}/melden/{id}',
        name: 'location_report_gone',
        methods: ['POST'],
        requirements: ['mapSlug' => MapSlug::PATTERN, 'id' => '\d+'],
    )]
    public function __invoke(
        Map $map,
        int $id,
        Request $request,
        LocationRepository $locations,
        LocationWorkflow $workflow,
        RateLimiterFactoryInterface $publicWriteLimiter,
        ClientRateLimit $rateLimit,
    ): Response {
        $mapParams = ['mapSlug' => $map->getSlug()];

        if (!$this->isCsrfTokenValid('report_gone', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if (!$rateLimit->tryConsume($publicWriteLimiter, $request)) {
            $this->addFlash('error', 'Zu viele Anfragen — bitte in ein paar Minuten erneut versuchen.');

            return $this->redirectToRoute('map_show', $mapParams);
        }

        $location = $locations->findVisibleOnMapById($map, $id);
        if ($location === null) {
            $this->addFlash('error', 'Eintrag nicht gefunden.');

            return $this->redirectToRoute('map_show', $mapParams);
        }

        $note = trim((string) $request->request->get('note', ''));
        $workflow->reportGone($location, null, $note !== '' ? $note : null);
        $this->addFlash('success', 'Danke für den Hinweis — wir prüfen das.');

        return $this->redirectToRoute('map_show', $mapParams);
    }
}
