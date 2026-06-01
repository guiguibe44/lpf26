<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TeamRecapCopy;
use App\Enum\TeamRecapCopyCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamRecapCopy>
 */
class TeamRecapCopyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamRecapCopy::class);
    }

    public function findActiveByCode(string $code): ?TeamRecapCopy
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.code = :code')
            ->andWhere('c.active = true')
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<TeamRecapCopy>
     */
    public function findActiveByCategoryOrdered(TeamRecapCopyCategory $category): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.category = :category')
            ->andWhere('c.active = true')
            ->setParameter('category', $category)
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<TeamRecapCopy>
     */
    public function findAllOrderedForAdmin(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.category', 'ASC')
            ->addOrderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.code', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
