<?php

namespace App\Repository;

use App\Entity\Location;
use App\Entity\Submission;
use App\Enum\ReviewStatus;
use App\Enum\SubmissionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Submission>
 */
class SubmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Submission::class);
    }

    /** @return list<Submission> */
    public function findOpenOrdered(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.reviewStatus = :status')
            ->setParameter('status', ReviewStatus::Open)
            ->orderBy('s.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countOpenStatusReportsFor(Location $location): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.location = :location')
            ->andWhere('s.type = :type')
            ->andWhere('s.reviewStatus = :status')
            ->setParameter('location', $location)
            ->setParameter('type', SubmissionType::StatusReport)
            ->setParameter('status', ReviewStatus::Open)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
