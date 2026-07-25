<?php

namespace App\Controller;

use App\Form\Data\LocationCorrectionData;
use App\Form\LocationCorrectionType;
use App\Repository\LocationRepository;
use App\Service\ClientRateLimit;
use App\Service\LocationImageStorage;
use App\Service\LocationWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class CorrectLocationController extends AbstractController
{
    #[Route('/ergaenzen', name: 'location_correct', methods: ['POST'])]
    public function __invoke(
        Request $request,
        LocationRepository $locations,
        LocationWorkflow $workflow,
        LocationImageStorage $images,
        RateLimiterFactoryInterface $publicWriteLimiter,
        ClientRateLimit $rateLimit,
    ): Response {
        $data = new LocationCorrectionData();
        $form = $this->createForm(LocationCorrectionType::class, $data);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Ergänzung ungültig — bitte Eingaben prüfen.');

            return $this->redirectToRoute('home');
        }

        if (!$rateLimit->tryConsume($publicWriteLimiter, $request)) {
            $this->addFlash('error', 'Zu viele Anfragen — bitte in ein paar Minuten erneut versuchen.');

            return $this->redirectToRoute('home');
        }

        if ($data->website !== null && trim($data->website) !== '') {
            $this->addFlash('success', 'Danke! Deine Ergänzung wird geprüft und erscheint nach Freigabe.');

            return $this->redirectToRoute('home');
        }

        if (!$data->hasContent()) {
            $this->addFlash('error', 'Bitte mindestens Text, Kategorie oder Foto angeben.');

            return $this->redirectToRoute('home');
        }

        $location = $locations->find($data->locationId);
        if ($location === null || !$location->getStatus()->isVisibleOnMap()) {
            $this->addFlash('error', 'Eintrag nicht gefunden oder nicht ergänzbar.');

            return $this->redirectToRoute('home');
        }

        $payload = $data->diffAgainstLocation($location, $data->toPayload());
        if ($data->image !== null) {
            $payload['image_path'] = $images->store($data->image);
        }

        if ($payload === []) {
            $this->addFlash('error', 'Keine Änderung erkannt — bitte Infos anpassen oder ein neues Foto wählen.');

            return $this->redirectToRoute('home');
        }

        $workflow->submitCorrection($location, $payload, $data->email);
        $this->addFlash('success', 'Danke! Deine Ergänzung wird geprüft und erscheint nach Freigabe.');

        return $this->redirectToRoute('home');
    }
}
