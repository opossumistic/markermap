<?php

namespace App\Controller\Api;

use App\Entity\Map;
use App\Map\LocationGeoJsonFactory;
use App\Map\MapSlug;
use App\Repository\LocationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class LocationApiController extends AbstractController
{
    #[Route(
        '/maps/{mapSlug}/api/locations.geojson',
        name: 'api_locations_geojson',
        methods: ['GET'],
        requirements: ['mapSlug' => MapSlug::PATTERN],
    )]
    public function geojson(Map $map, LocationRepository $locations, LocationGeoJsonFactory $geoJson): JsonResponse
    {
        return $this->json($geoJson->featureCollection($locations->findVisibleOnMap($map)));
    }
}
