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

/**
 * Unscoped geocode for platform flows (map create viewport picker).
 */
final class PlatformGeocodeApiController extends AbstractController
{
    #[Route('/api/platform/geocode', name: 'api_platform_geocode', methods: ['GET'])]
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
