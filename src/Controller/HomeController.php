<?php

namespace App\Controller;

use App\Form\Data\LocationCorrectionData;
use App\Form\Data\NewLocationSubmissionData;
use App\Form\LocationCorrectionType;
use App\Form\NewLocationSubmissionType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(MAP_STYLE_URL)%')]
        private readonly string $mapStyleUrl,
    ) {
    }

    #[Route('/', name: 'home')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(NewLocationSubmissionType::class, new NewLocationSubmissionData());
        $correctionForm = $this->createForm(LocationCorrectionType::class, new LocationCorrectionData());

        $focusId = $request->query->getInt('focus');

        return $this->render('home/index.html.twig', [
            'mapStyleUrl' => $this->mapStyleUrl,
            'form' => $form,
            'correctionForm' => $correctionForm,
            'openAdd' => $request->query->getBoolean('add'),
            'focusId' => $focusId > 0 ? $focusId : null,
        ]);
    }
}
