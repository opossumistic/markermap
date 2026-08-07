<?php

namespace App\Controller;

use App\Map\MapSlug;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Back-compat redirects from the former single-tenant URL layout.
 * 307 keeps POST method/body for old form/API clients.
 */
final class LegacyRedirectController extends AbstractController
{
    #[Route('/vorschlagen', name: 'legacy_location_submit', methods: ['GET', 'POST'])]
    public function submit(Request $request): Response
    {
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('map_show', [
                'mapSlug' => MapSlug::DEFAULT,
                'add' => 1,
            ], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->redirectToRoute('location_submit', ['mapSlug' => MapSlug::DEFAULT], Response::HTTP_TEMPORARY_REDIRECT);
    }

    #[Route('/ergaenzen', name: 'legacy_location_correct', methods: ['POST'])]
    public function correct(): Response
    {
        return $this->redirectToRoute('location_correct', ['mapSlug' => MapSlug::DEFAULT], Response::HTTP_TEMPORARY_REDIRECT);
    }

    #[Route('/melden/{id}', name: 'legacy_location_report', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function report(int $id): Response
    {
        return $this->redirectToRoute('location_report_gone', [
            'mapSlug' => MapSlug::DEFAULT,
            'id' => $id,
        ], Response::HTTP_TEMPORARY_REDIRECT);
    }

    #[Route('/api/locations.geojson', name: 'legacy_api_locations_geojson', methods: ['GET'])]
    public function geojson(): Response
    {
        return $this->redirectToRoute('api_locations_geojson', ['mapSlug' => MapSlug::DEFAULT], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/api/reverse-geocode', name: 'legacy_api_reverse_geocode', methods: ['GET'])]
    public function reverseGeocode(Request $request): Response
    {
        return $this->redirectToRoute(
            'api_reverse_geocode',
            ['mapSlug' => MapSlug::DEFAULT] + $request->query->all(),
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }

    #[Route('/api/geocode', name: 'legacy_api_geocode', methods: ['GET'])]
    public function geocode(Request $request): Response
    {
        return $this->redirectToRoute(
            'api_geocode',
            ['mapSlug' => MapSlug::DEFAULT] + $request->query->all(),
            Response::HTTP_MOVED_PERMANENTLY,
        );
    }
}
