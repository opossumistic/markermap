<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractController
{
    #[Route('/impressum', name: 'legal_impressum', methods: ['GET'])]
    public function impressum(): Response
    {
        return $this->noindexLegalResponse('legal/impressum.html.twig');
    }

    #[Route('/datenschutz', name: 'legal_datenschutz', methods: ['GET'])]
    public function datenschutz(): Response
    {
        return $this->noindexLegalResponse('legal/datenschutz.html.twig');
    }

    private function noindexLegalResponse(string $template): Response
    {
        $response = $this->render($template);
        $response->headers->set('X-Robots-Tag', 'noindex, follow');

        return $response;
    }
}
