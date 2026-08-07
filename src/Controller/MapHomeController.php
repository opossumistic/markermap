<?php

namespace App\Controller;

use App\Entity\Map;
use App\Form\Data\LocationCorrectionData;
use App\Form\Data\NewLocationSubmissionData;
use App\Form\LocationCorrectionType;
use App\Form\NewLocationSubmissionType;
use App\Map\MapSlug;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MapHomeController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(MAP_STYLE_URL)%')]
        private readonly string $mapStyleUrl,
    ) {
    }

    #[Route(
        '/maps/{mapSlug}',
        name: 'map_show',
        methods: ['GET'],
        requirements: ['mapSlug' => MapSlug::PATTERN],
    )]
    public function __invoke(Map $map, Request $request): Response
    {
        $formOpts = ['categories_enabled' => $map->usesCategories()];
        $form = $this->createForm(NewLocationSubmissionType::class, new NewLocationSubmissionData(), $formOpts);
        $correctionForm = $this->createForm(LocationCorrectionType::class, new LocationCorrectionData(), $formOpts);

        $focusId = $request->query->getInt('focus');

        return $this->render('home/index.html.twig', [
            'map' => $map,
            'mapStyleUrl' => $this->mapStyleUrl,
            'form' => $form,
            'correctionForm' => $correctionForm,
            'openAdd' => $request->query->getBoolean('add'),
            'focusId' => $focusId > 0 ? $focusId : null,
        ]);
    }
}
