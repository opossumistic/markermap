<?php

namespace App\Controller;

use App\Enum\AuthTokenPurpose;
use App\Security\MapOwnerAuthenticator;
use App\Service\AuthTokenService;
use App\Service\MapRegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    #[Route('/auth/verify-map/{selector}/{token}', name: 'auth_map_verify', methods: ['GET'], requirements: [
        'selector' => '[a-f0-9]{32}',
        'token' => '[a-f0-9]{64}',
    ])]
    public function verifyMap(
        string $selector,
        string $token,
        MapRegistrationService $registration,
        Security $security,
    ): Response {
        try {
            $map = $registration->verifyAndActivate($selector, $token);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('platform_home');
        }

        $owner = $map->getOwner();
        if ($owner !== null) {
            $security->login($owner, MapOwnerAuthenticator::class, 'map_admin');
        }

        $this->addFlash(
            'success',
            sprintf('Map „%s“ ist live. Trag den ersten Ort ein — Moderation findest du unten unter „Moderation“.', $map->getName()),
        );

        return $this->redirectToRoute('map_show', [
            'mapSlug' => $map->getSlug(),
            'add' => 1,
        ]);
    }

    #[Route('/auth/magic/{selector}/{token}', name: 'auth_magic_login', methods: ['GET'], requirements: [
        'selector' => '[a-f0-9]{32}',
        'token' => '[a-f0-9]{64}',
    ])]
    public function magicLogin(
        string $selector,
        string $token,
        AuthTokenService $authTokens,
        Security $security,
    ): Response {
        try {
            $authToken = $authTokens->consume($selector, $token, AuthTokenPurpose::MagicLogin);
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('platform_home');
        }

        $map = $authToken->getMap();
        $security->login($authToken->getUser(), MapOwnerAuthenticator::class, 'map_admin');

        if ($map === null) {
            return $this->redirectToRoute('platform_home');
        }

        $this->addFlash('success', 'Eingeloggt.');

        return $this->redirectToRoute('map_admin_inbox', ['mapSlug' => $map->getSlug()]);
    }
}
