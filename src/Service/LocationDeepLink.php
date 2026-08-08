<?php

namespace App\Service;

use App\Entity\Location;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Canonical public URL for a location (QR / share). Soft-open uses internal ?focus=.
 */
final class LocationDeepLink
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function canonicalPath(Location $location): string
    {
        $publicId = $location->getPublicId();
        if ($publicId === '') {
            throw new \LogicException('Location has no publicId.');
        }

        return $this->urlGenerator->generate('location_landing', [
            'mapSlug' => $location->getMap()->getSlug(),
            'publicId' => $publicId,
        ]);
    }

    public function absoluteUrl(Location $location): string
    {
        $publicId = $location->getPublicId();
        if ($publicId === '') {
            throw new \LogicException('Location has no publicId.');
        }

        return $this->urlGenerator->generate('location_landing', [
            'mapSlug' => $location->getMap()->getSlug(),
            'publicId' => $publicId,
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public function mapFocusPath(Location $location): string
    {
        $id = $location->getId();
        if ($id === null) {
            throw new \LogicException('Location must be persisted before focus URL.');
        }

        return $this->urlGenerator->generate('map_show', [
            'mapSlug' => $location->getMap()->getSlug(),
            'focus' => $id,
        ]);
    }
}
