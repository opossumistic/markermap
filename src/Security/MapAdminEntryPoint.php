<?php

namespace App\Security;

use App\Map\MapSlug;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class MapAdminEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $slug = $this->extractSlug($request->getPathInfo()) ?? MapSlug::DEFAULT;

        return new RedirectResponse($this->urlGenerator->generate('map_admin_login', [
            'mapSlug' => $slug,
        ]));
    }

    private function extractSlug(string $path): ?string
    {
        if (preg_match('#^/maps/('.MapSlug::PATTERN.')/admin#', $path, $m)) {
            return $m[1];
        }

        return null;
    }
}
