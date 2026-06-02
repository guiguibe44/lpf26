<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DashboardEditorial;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DashboardEditorial>
 */
class DashboardEditorialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DashboardEditorial::class);
    }

    public function findLatestPublishedAt(\DateTimeImmutable $now): ?DashboardEditorial
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.author', 'a')
            ->addSelect('a')
            ->andWhere('e.published = true')
            ->andWhere('e.publishedAt <= :now')
            ->setParameter('now', $now)
            ->orderBy('e.publishedAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
