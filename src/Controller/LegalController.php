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
        return $this->render('legal/impressum.html.twig');
    }

    #[Route('/datenschutz', name: 'legal_datenschutz', methods: ['GET'])]
    public function datenschutz(): Response
    {
        return $this->render('legal/datenschutz.html.twig');
    }
}
