<?php

namespace App\Twig;

use App\Entity\Map;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MapSecurityExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_map_owner', $this->isMapOwner(...)),
        ];
    }

    public function isMapOwner(?Map $map): bool
    {
        if ($map === null) {
            return false;
        }

        $user = $this->security->getUser();

        return $user instanceof User && $map->isOwnedBy($user);
    }
}
