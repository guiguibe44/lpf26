<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TeamRecapGif;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamRecapGif>
 */
class TeamRecapGifRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamRecapGif::class);
    }

    /**
     * @return list<string>
     */
    public function findActivePathsBySlot(string $slot): array
    {
        /** @var list<string> $paths */
        $paths = $this->createQueryBuilder('g')
            ->select('g.path')
            ->andWhere('g.slot = :slot')
            ->andWhere('g.active = true')
            ->andWhere('g.path IS NOT NULL')
            ->andWhere('g.path != :empty')
            ->setParameter('slot', $slot)
            ->setParameter('empty', '')
            ->orderBy('g.sortOrder', 'ASC')
            ->addOrderBy('g.id', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter($paths, static fn (string $path): bool => '' !== trim($path)));
    }

    /**
     * @return list<string>
     */
    public function findActiveSlots(): array
    {
        /** @var list<string> $slots */
        $slots = $this->createQueryBuilder('g')
            ->select('DISTINCT g.slot')
            ->andWhere('g.active = true')
            ->andWhere('g.slot IS NOT NULL')
            ->andWhere('g.slot != :empty')
            ->setParameter('empty', '')
            ->orderBy('g.slot', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter($slots, static fn (string $slot): bool => '' !== trim($slot)));
    }

    /**
     * @return list<string>
     */
    public function findAllActivePaths(): array
    {
        /** @var list<string> $paths */
        $paths = $this->createQueryBuilder('g')
            ->select('g.path')
            ->andWhere('g.active = true')
            ->andWhere('g.path IS NOT NULL')
            ->andWhere('g.path != :empty')
            ->setParameter('empty', '')
            ->orderBy('g.sortOrder', 'ASC')
            ->addOrderBy('g.id', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_filter($paths, static fn (string $path): bool => '' !== trim($path)));
    }
}
