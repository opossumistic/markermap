<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Single admin from env — username not guessable as hard-coded "admin".
 *
 * @implements UserProviderInterface<InMemoryUser>
 */
final class EnvAdminUserProvider implements UserProviderInterface
{
    public function __construct(
        #[Autowire('%env(ADMIN_USERNAME)%')]
        private readonly string $username,
        #[Autowire('%env(ADMIN_PASSWORD)%')]
        private readonly string $passwordHash,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (!hash_equals($this->username, $identifier)) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return new InMemoryUser($this->username, $this->passwordHash, ['ROLE_ADMIN']);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof InMemoryUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return InMemoryUser::class === $class || is_subclass_of($class, InMemoryUser::class);
    }
}
