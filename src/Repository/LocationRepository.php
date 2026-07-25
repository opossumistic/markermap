<?php

namespace App\Repository;

use App\Entity\Location;
use App\Enum\LocationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Location>
 */
class LocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    /** @return list<Location> */
    public function findVisibleOnMap(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('statuses', [LocationStatus::Pending, LocationStatus::Active, LocationStatus::Disputed])
            ->orderBy('l.title', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
