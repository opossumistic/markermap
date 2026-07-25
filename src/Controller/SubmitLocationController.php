<?php

namespace App\Controller;

use App\Form\Data\LocationCorrectionData;
use App\Form\Data\NewLocationSubmissionData;
use App\Form\LocationCorrectionType;
use App\Form\NewLocationSubmissionType;
use App\Service\LocationImageStorage;
use App\Service\LocationWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SubmitLocationController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(MAP_STYLE_URL)%')]
        private readonly string $mapStyleUrl,
    ) {
    }

    #[Route('/vorschlagen', name: 'location_submit', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        LocationWorkflow $workflow,
        LocationImageStorage $images,
    ): Response {
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('home', ['add' => 1]);
        }

        $data = new NewLocationSubmissionData();
        $form = $this->createForm(NewLocationSubmissionType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($data->website !== null && trim($data->website) !== '') {
                $this->addFlash('success', 'Danke! Dein Vorschlag ist als ausgegrauter Punkt auf der Karte und wird geprüft.');

                return $this->redirectToRoute('home');
            }

            $payload = $data->toPayload();
            if ($data->image !== null) {
                $payload['image_path'] = $images->store($data->image);
            }

            $workflow->submitNew($payload, $data->email);
            $this->addFlash('success', 'Danke! Dein Vorschlag ist als ausgegrauter Punkt auf der Karte und wird geprüft.');

            return $this->redirectToRoute('home');
        }

        if ($form->isSubmitted()) {
            $this->addFlash('error', 'Bitte prüfe die markierten Felder und setze einen Standort auf der Karte.');
        }

        return $this->render('home/index.html.twig', [
            'mapStyleUrl' => $this->mapStyleUrl,
            'form' => $form,
            'correctionForm' => $this->createForm(LocationCorrectionType::class, new LocationCorrectionData()),
            'openAdd' => true,
        ]);
    }
}
