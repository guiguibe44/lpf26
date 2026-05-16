<?php

namespace App\Repository;

use App\Entity\But;
use App\Entity\Buteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<But>
 */
class ButRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, But::class);
    }

    public function countForButeur(Buteur $buteur): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.buteur = :buteur')
            ->setParameter('buteur', $buteur)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumPointsAttribuesForButeur(Buteur $buteur): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COALESCE(SUM(b.pointsAttribues), 0)')
            ->andWhere('b.buteur = :buteur')
            ->setParameter('buteur', $buteur)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<But>
     */
    public function findForButeurOrderedByMatch(Buteur $buteur): array
    {
        return $this->createQueryBuilder('b')
            ->addSelect('m')
            ->join('b.matchRef', 'm')
            ->andWhere('b.buteur = :buteur')
            ->setParameter('buteur', $buteur)
            ->orderBy('m.dateHeure', 'DESC')
            ->addOrderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
