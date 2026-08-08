<?php

namespace App\Controller;

use App\Entity\Map;
use App\Enum\LocationStatus;
use App\Map\LocationPublicId;
use App\Map\MapSlug;
use App\Repository\LocationRepository;
use App\Service\LocationDeepLink;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LocationLandingController extends AbstractController
{
    #[Route(
        '/maps/{mapSlug}/l/{publicId}',
        name: 'location_landing',
        methods: ['GET'],
        requirements: [
            'mapSlug' => MapSlug::PATTERN,
            'publicId' => LocationPublicId::PATTERN,
        ],
    )]
    public function __invoke(
        Map $map,
        string $publicId,
        LocationRepository $locations,
        LocationDeepLink $deepLink,
    ): Response {
        $location = $locations->findOneByMapAndPublicId($map, $publicId);
        if ($location === null) {
            throw $this->createNotFoundException();
        }

        $status = $location->getStatus();
        $onMap = $status->isVisibleOnMap();

        return $this->render('location/landing.html.twig', [
            'map' => $map,
            'location' => $location,
            'onMap' => $onMap,
            'isRemoved' => $status === LocationStatus::Removed,
            'mapFocusPath' => $onMap ? $deepLink->mapFocusPath($location) : null,
            'mapPath' => $this->generateUrl('map_show', ['mapSlug' => $map->getSlug()]),
        ]);
    }
}
