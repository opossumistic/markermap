<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => User::normalizeEmail($email)]);
    }

    /**
     * Find or create by email (no flush).
     */
    public function getOrCreate(string $email): User
    {
        $normalized = User::normalizeEmail($email);
        $user = $this->findOneByEmail($normalized);
        if ($user !== null) {
            return $user;
        }

        $user = new User($normalized);
        $this->getEntityManager()->persist($user);

        return $user;
    }
}
