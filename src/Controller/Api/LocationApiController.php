<?php

namespace App\Controller\Api;

use App\Map\LocationGeoJsonFactory;
use App\Repository\LocationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class LocationApiController extends AbstractController
{
    #[Route('/api/locations.geojson', name: 'api_locations_geojson', methods: ['GET'])]
    public function geojson(LocationRepository $locations, LocationGeoJsonFactory $geoJson): JsonResponse
    {
        return $this->json($geoJson->featureCollection($locations->findVisibleOnMap()));
    }
}
