<?php

namespace App\Controller\Admin;

use App\Entity\Map;
use App\Enum\MapStatus;
use App\Repository\MapRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/maps')]
#[IsGranted('ROLE_ADMIN')]
final class MapsController extends AbstractController
{
    #[Route('', name: 'admin_maps', methods: ['GET'])]
    public function index(MapRepository $maps): Response
    {
        return $this->render('admin/maps.html.twig', [
            'maps' => $maps->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/{id}/disable', name: 'admin_map_disable', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function disable(Map $map, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('disable_map'.$map->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $map->disable();
        $em->flush();
        $this->addFlash('success', sprintf('Map „%s“ deaktiviert.', $map->getName()));

        return $this->redirectToRoute('admin_maps');
    }

    #[Route('/{id}/enable', name: 'admin_map_enable', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function enable(Map $map, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('enable_map'.$map->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        if ($map->getStatus() === MapStatus::PendingVerify) {
            $this->addFlash('error', 'Pending Maps bitte über den Verify-Link des Owners freischalten.');

            return $this->redirectToRoute('admin_maps');
        }

        $map->activate();
        $em->flush();
        $this->addFlash('success', sprintf('Map „%s“ wieder aktiv.', $map->getName()));

        return $this->redirectToRoute('admin_maps');
    }
}
