<?php

namespace App\Controller\Api;

use App\Repository\MapRepository;
use App\Service\ClientRateLimit;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class PlatformSlugApiController extends AbstractController
{
    #[Route('/api/platform/slug-suggest', name: 'api_platform_slug_suggest', methods: ['GET'])]
    public function suggest(
        Request $request,
        MapRepository $maps,
        RateLimiterFactoryInterface $geocodeLimiter,
        ClientRateLimit $rateLimit,
    ): JsonResponse {
        // Reuse geocode limiter — same anonymous abuse surface.
        $rateLimit->enforce($geocodeLimiter, $request);

        $name = trim((string) $request->query->get('name', ''));
        if ($name === '') {
            return $this->json(['error' => 'name required'], Response::HTTP_BAD_REQUEST);
        }

        $slug = $maps->allocateUniqueSlug($name);

        return $this->json([
            'slug' => $slug,
            'path' => '/maps/'.$slug,
        ]);
    }
}
