<?php

namespace App\Controller;

use App\Entity\Map;
use App\Form\Data\LocationCorrectionData;
use App\Form\Data\NewLocationSubmissionData;
use App\Form\LocationCorrectionType;
use App\Form\NewLocationSubmissionType;
use App\Map\MapSlug;
use App\Service\ClientRateLimit;
use App\Service\LocationImageStorage;
use App\Service\LocationWorkflow;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class SubmitLocationController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(MAP_STYLE_URL)%')]
        private readonly string $mapStyleUrl,
    ) {
    }

    #[Route(
        '/maps/{mapSlug}/vorschlagen',
        name: 'location_submit',
        methods: ['GET', 'POST'],
        requirements: ['mapSlug' => MapSlug::PATTERN],
    )]
    public function __invoke(
        Map $map,
        Request $request,
        LocationWorkflow $workflow,
        LocationImageStorage $images,
        RateLimiterFactoryInterface $publicWriteLimiter,
        ClientRateLimit $rateLimit,
    ): Response {
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('map_show', ['mapSlug' => $map->getSlug(), 'add' => 1]);
        }

        $formOpts = ['categories_enabled' => $map->usesCategories()];
        $data = new NewLocationSubmissionData();
        $form = $this->createForm(NewLocationSubmissionType::class, $data, $formOpts);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$rateLimit->tryConsume($publicWriteLimiter, $request)) {
                $this->addFlash('error', 'Zu viele Anfragen — bitte in ein paar Minuten erneut versuchen.');

                return $this->redirectToRoute('map_show', ['mapSlug' => $map->getSlug()]);
            }

            if ($data->website !== null && trim($data->website) !== '') {
                $this->addFlash('success', 'Danke! Dein Vorschlag ist als ausgegrauter Punkt auf der Karte und wird geprüft.');

                return $this->redirectToRoute('map_show', ['mapSlug' => $map->getSlug()]);
            }

            if ($map->hasBounds() && !$map->containsCoordinates((float) $data->lat, (float) $data->lng)) {
                $this->addFlash('error', 'Standort liegt außerhalb des erlaubten Kartenbereichs.');

                return $this->redirectToRoute('map_show', ['mapSlug' => $map->getSlug(), 'add' => 1]);
            }

            $payload = $data->toPayload();
            if ($data->image !== null) {
                $payload['image_path'] = $images->store($data->image);
            }

            $submission = $workflow->submitNew($map, $payload, $data->email);
            $this->addFlash('success', 'Danke! Dein Vorschlag ist als ausgegrauter Punkt auf der Karte und wird geprüft.');

            $locationId = $submission->getLocation()?->getId();

            return $this->redirectToRoute('map_show', array_filter([
                'mapSlug' => $map->getSlug(),
                'focus' => $locationId,
            ]));
        }

        if ($form->isSubmitted()) {
            $this->addFlash('error', 'Bitte prüfe die markierten Felder und setze einen Standort auf der Karte.');
        }

        return $this->render('home/index.html.twig', [
            'map' => $map,
            'mapStyleUrl' => $this->mapStyleUrl,
            'form' => $form,
            'correctionForm' => $this->createForm(LocationCorrectionType::class, new LocationCorrectionData(), $formOpts),
            'openAdd' => true,
            'focusId' => null,
        ]);
    }
}
