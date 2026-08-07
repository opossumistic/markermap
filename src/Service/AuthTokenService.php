<?php

namespace App\Service;

use App\Entity\AuthToken;
use App\Entity\Map;
use App\Entity\User;
use App\Enum\AuthTokenPurpose;
use App\Repository\AuthTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creates and consumes one-time auth tokens (map verify + magic login).
 */
final class AuthTokenService
{
    private const SELECTOR_BYTES = 16;
    private const VERIFIER_BYTES = 32;

    public const MAP_VERIFY_TTL = 'P2D';
    public const MAGIC_LOGIN_TTL = 'PT20M';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthTokenRepository $tokens,
    ) {
    }

    /**
     * @return array{token: AuthToken, plain: string}
     */
    public function issue(AuthTokenPurpose $purpose, User $user, ?Map $map, string $ttlSpec): array
    {
        $selector = bin2hex(random_bytes(self::SELECTOR_BYTES));
        $plain = bin2hex(random_bytes(self::VERIFIER_BYTES));
        $hash = hash('sha256', $plain);
        $expiresAt = (new \DateTimeImmutable())->add(new \DateInterval($ttlSpec));

        $token = new AuthToken($selector, $hash, $purpose, $user, $expiresAt, $map);
        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return ['token' => $token, 'plain' => $plain];
    }

    public function consume(string $selector, string $plain, AuthTokenPurpose $purpose): AuthToken
    {
        $token = $this->tokens->findValidBySelector($selector, $purpose);
        if ($token === null) {
            throw new \DomainException('Link ungültig oder abgelaufen.');
        }

        if (!hash_equals($token->getVerifierHash(), hash('sha256', $plain))) {
            throw new \DomainException('Link ungültig oder abgelaufen.');
        }

        $token->consume();
        $this->entityManager->flush();

        return $token;
    }
}
