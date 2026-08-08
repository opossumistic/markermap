<?php

namespace App\Repository;

use App\Entity\Location;
use App\Entity\Map;
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
    public function findVisibleOnMap(Map $map): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.map = :map')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('map', $map)
            ->setParameter('statuses', [LocationStatus::Pending, LocationStatus::Active, LocationStatus::Disputed])
            ->orderBy('l.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Map-visible locations for admin moderation (newest first).
     *
     * @return list<Location>
     */
    public function findModeratable(?Map $map = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.status IN (:statuses)')
            ->setParameter('statuses', [LocationStatus::Pending, LocationStatus::Active, LocationStatus::Disputed])
            ->orderBy('l.id', 'DESC');

        if ($map !== null) {
            $qb->andWhere('l.map = :map')->setParameter('map', $map);
        }

        return $qb->getQuery()->getResult();
    }

    public function findVisibleOnMapById(Map $map, int $id): ?Location
    {
        $location = $this->find($id);
        if ($location === null
            || $location->getMap()->getId() !== $map->getId()
            || !$location->getStatus()->isVisibleOnMap()
        ) {
            return null;
        }

        return $location;
    }

    public function findOneByMapAndPublicId(Map $map, string $publicId): ?Location
    {
        return $this->findOneBy([
            'map' => $map,
            'publicId' => $publicId,
        ]);
    }
}
