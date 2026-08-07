<?php

namespace App\Controller;

use App\Map\MapSlug;
use App\Repository\MapRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Platform landing: list public maps (Phase 5 can thicken this).
 */
final class PlatformController extends AbstractController
{
    #[Route('/', name: 'platform_home', methods: ['GET'])]
    public function index(MapRepository $maps): Response
    {
        return $this->render('platform/index.html.twig', [
            'maps' => $maps->findPublicOrdered(),
            'defaultSlug' => MapSlug::DEFAULT,
        ]);
    }

    #[Route('/maps', name: 'maps_index', methods: ['GET'], priority: 10)]
    public function mapsIndex(): Response
    {
        return $this->redirectToRoute('platform_home', status: Response::HTTP_MOVED_PERMANENTLY);
    }
}
