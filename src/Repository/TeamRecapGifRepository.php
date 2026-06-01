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
}
