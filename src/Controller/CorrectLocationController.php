<?php

namespace App\Controller;

use App\Entity\Map;
use App\Form\Data\LocationCorrectionData;
use App\Form\LocationCorrectionType;
use App\Map\MapSlug;
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
    #[Route(
        '/maps/{mapSlug}/ergaenzen',
        name: 'location_correct',
        methods: ['POST'],
        requirements: ['mapSlug' => MapSlug::PATTERN],
    )]
    public function __invoke(
        Map $map,
        Request $request,
        LocationRepository $locations,
        LocationWorkflow $workflow,
        LocationImageStorage $images,
        RateLimiterFactoryInterface $publicWriteLimiter,
        ClientRateLimit $rateLimit,
    ): Response {
        $mapParams = ['mapSlug' => $map->getSlug()];

        $data = new LocationCorrectionData();
        $form = $this->createForm(LocationCorrectionType::class, $data, [
            'categories_enabled' => $map->usesCategories(),
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Änderung ungültig — bitte Eingaben prüfen.');

            return $this->redirectToRoute('map_show', $mapParams);
        }

        if (!$rateLimit->tryConsume($publicWriteLimiter, $request)) {
            $this->addFlash('error', 'Zu viele Anfragen — bitte in ein paar Minuten erneut versuchen.');

            return $this->redirectToRoute('map_show', $mapParams);
        }

        if ($data->website !== null && trim($data->website) !== '') {
            $this->addFlash('success', 'Danke! Deine Änderung wird geprüft und erscheint nach Freigabe.');

            return $this->redirectToRoute('map_show', $mapParams);
        }

        if (!$data->hasContent()) {
            $this->addFlash('error', 'Bitte mindestens Text, Kategorie oder Foto angeben.');

            return $this->redirectToRoute('map_show', $mapParams);
        }

        $location = $locations->findVisibleOnMapById($map, (int) $data->locationId);
        if ($location === null) {
            $this->addFlash('error', 'Eintrag nicht gefunden oder nicht änderbar.');

            return $this->redirectToRoute('map_show', $mapParams);
        }

        $payload = $data->diffAgainstLocation($location, $data->toPayload());
        if ($data->image !== null) {
            $payload['image_path'] = $images->store($data->image);
        }

        if ($payload === []) {
            $this->addFlash('error', 'Keine Änderung erkannt — bitte Infos anpassen oder ein neues Foto wählen.');

            return $this->redirectToRoute('map_show', $mapParams);
        }

        // Moderation-only: never part of the editable diff / Location.applyPayload.
        if (($reason = $data->moderationReason()) !== null) {
            $payload['reason'] = $reason;
        }

        $workflow->submitCorrection($location, $payload, $data->email);
        $this->addFlash('success', 'Danke! Deine Änderung wird geprüft und erscheint nach Freigabe.');

        return $this->redirectToRoute('map_show', $mapParams);
    }
}
