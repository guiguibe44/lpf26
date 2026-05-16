<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Joker;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Joker>
 */
class JokerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Joker::class);
    }

    /**
     * @return list<Joker>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('j')
            ->orderBy('j.sortOrder', 'ASC')
            ->addOrderBy('j.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
