<?php

namespace App\Controller\Admin;

use App\Entity\Location;
use App\Repository\LocationRepository;
use App\Service\LocationWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class LocationsController extends AbstractController
{
    #[Route('/locations', name: 'admin_locations', methods: ['GET'])]
    public function index(LocationRepository $locations): Response
    {
        return $this->render('admin/locations.html.twig', [
            'locations' => $locations->findModeratable(),
        ]);
    }

    #[Route('/locations/{id}/remove', name: 'admin_location_remove', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function remove(Location $location, Request $request, LocationWorkflow $workflow): Response
    {
        if (!$this->isCsrfTokenValid('remove'.$location->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $redirect = $request->request->getString('redirect', 'admin_locations');
        if (!\in_array($redirect, ['admin_locations', 'admin_inbox'], true)) {
            $redirect = 'admin_locations';
        }

        try {
            $workflow->adminSoftRemove($location);
            $this->addFlash('success', sprintf('Location #%d von der Karte entfernt.', $location->getId()));
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute($redirect);
    }
}
