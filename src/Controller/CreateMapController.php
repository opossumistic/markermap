<?php

namespace App\Controller;

use App\Form\CreateMapType;
use App\Form\Data\CreateMapData;
use App\Service\ClientRateLimit;
use App\Service\MapRegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final class CreateMapController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(MAP_STYLE_URL)%')]
        private readonly string $mapStyleUrl,
    ) {
    }

    #[Route('/maps/new', name: 'map_create', methods: ['GET', 'POST'], priority: 10)]
    public function __invoke(
        Request $request,
        MapRegistrationService $registration,
        RateLimiterFactoryInterface $mapCreateLimiter,
        ClientRateLimit $rateLimit,
    ): Response {
        $data = new CreateMapData();
        $form = $this->createForm(CreateMapType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$rateLimit->tryConsume($mapCreateLimiter, $request)) {
                $this->addFlash('error', 'Zu viele Anfragen — bitte später erneut versuchen.');

                return $this->redirectToRoute('map_create');
            }

            if ($data->website !== null && trim($data->website) !== '') {
                $this->addFlash('success', 'Prüfe dein Postfach — wir haben dir einen Bestätigungslink geschickt.');

                return $this->redirectToRoute('platform_home');
            }

            try {
                $registration->register(
                    (string) $data->name,
                    (string) $data->slug,
                    (string) $data->email,
                    (float) $data->centerLat,
                    (float) $data->centerLng,
                    (float) $data->defaultZoom,
                    $data->description,
                );
                $this->addFlash('success', 'Prüfe dein Postfach — wir haben dir einen Bestätigungslink geschickt.');

                return $this->redirectToRoute('platform_home');
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('map/create.html.twig', [
            'form' => $form,
            'mapStyleUrl' => $this->mapStyleUrl,
        ]);
    }
}
