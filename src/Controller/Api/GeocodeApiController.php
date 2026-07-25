<?php

namespace App\Controller\Api;

use App\Service\ClientRateLimit;
use App\Service\ReverseGeocoder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class GeocodeApiController extends AbstractController
{
    #[Route('/api/reverse-geocode', name: 'api_reverse_geocode', methods: ['GET'])]
    public function reverse(
        Request $request,
        ReverseGeocoder $geocoder,
        RateLimiterFactoryInterface $geocodeLimiter,
        ClientRateLimit $rateLimit,
    ): JsonResponse {
        $rateLimit->enforce($geocodeLimiter, $request);

        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');

        if (!is_numeric($lat) || !is_numeric($lng)) {
            return $this->json(['error' => 'lat and lng required'], Response::HTTP_BAD_REQUEST);
        }

        $result = $geocoder->reverse((float) $lat, (float) $lng);
        if ($result === null) {
            return $this->json(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($result);
    }

    #[Route('/api/geocode', name: 'api_geocode', methods: ['GET'])]
    public function search(
        Request $request,
        ReverseGeocoder $geocoder,
        RateLimiterFactoryInterface $geocodeLimiter,
        ClientRateLimit $rateLimit,
    ): JsonResponse {
        $rateLimit->enforce($geocodeLimiter, $request);

        $query = trim((string) $request->query->get('q', ''));
        if (mb_strlen($query) < 3) {
            return $this->json(['error' => 'q must be at least 3 characters'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'results' => $geocoder->search($query),
        ]);
    }
}
