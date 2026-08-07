<?php

namespace App\Security;

use App\Entity\Map;
use App\Entity\User;
use App\Enum\MapStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Map>
 */
final class MapAdminVoter extends Voter
{
    public const ADMIN = 'MAP_ADMIN';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ADMIN && $subject instanceof Map;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        unset($attribute);
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof Map) {
            return false;
        }

        if ($subject->getStatus() === MapStatus::Disabled) {
            return false;
        }

        return $subject->isOwnedBy($user);
    }
}
