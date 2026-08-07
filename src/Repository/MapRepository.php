<?php

namespace App\Repository;

use App\Entity\Map;
use App\Enum\MapStatus;
use App\Map\MapSlug;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Map>
 */
class MapRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Map::class);
    }

    public function findOneBySlug(string $slug): ?Map
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Returns a free slug: base, or base-2, base-3, … (skips reserved names).
     */
    public function allocateUniqueSlug(string $base): string
    {
        $base = MapSlug::fromTitle($base);
        if ($base === '') {
            $base = 'map';
        }

        $candidate = $base;
        $n = 2;
        while (MapSlug::isReserved($candidate) || $this->findOneBySlug($candidate) !== null) {
            $candidate = $base.'-'.$n;
            ++$n;
            if ($n > 9999) {
                throw new \RuntimeException('Could not allocate a unique map slug.');
            }
        }

        return $candidate;
    }

    public function findActiveBySlug(string $slug): ?Map
    {
        $map = $this->findOneBySlug($slug);

        return $map !== null && $map->isPubliclyAccessible() ? $map : null;
    }

    /** @return list<Map> */
    public function findPublicOrdered(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.status = :status')
            ->setParameter('status', MapStatus::Active)
            ->orderBy('m.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Ensures Tenant #1 exists (idempotent for seeds/commands).
     */
    public function getDefaultMap(): Map
    {
        $map = $this->findOneBySlug(MapSlug::DEFAULT);
        if ($map !== null) {
            return $map;
        }

        $map = Map::createTauschboxenDefault();
        $this->getEntityManager()->persist($map);
        $this->getEntityManager()->flush();

        return $map;
    }
}
