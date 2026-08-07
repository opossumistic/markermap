<?php

namespace App\Repository;

use App\Entity\AuthToken;
use App\Enum\AuthTokenPurpose;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuthToken>
 */
class AuthTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthToken::class);
    }

    public function findValidBySelector(string $selector, AuthTokenPurpose $purpose): ?AuthToken
    {
        $token = $this->findOneBy([
            'selector' => $selector,
            'purpose' => $purpose,
        ]);

        if ($token === null || $token->isConsumed() || $token->isExpired()) {
            return null;
        }

        return $token;
    }
}
