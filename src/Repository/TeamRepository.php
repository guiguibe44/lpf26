<?php

namespace App\Repository;

use App\Entity\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Team>
 */
class TeamRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Team::class);
    }

    public function findOneWithMembersAndPlayers(int $id): ?Team
    {
        return $this->createQueryBuilder('t')
            ->addSelect('m', 'u')
            ->leftJoin('t.members', 'm')
            ->leftJoin('m.player', 'u')
            ->andWhere('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Team>
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.name', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Team>
     */
    public function findAllWithMembersAndPlayers(): array
    {
        return $this->createQueryBuilder('t')
            ->addSelect('m', 'u', 'b', 'bp')
            ->leftJoin('t.members', 'm')
            ->leftJoin('m.player', 'u')
            ->leftJoin('u.buteurChoisi', 'b')
            ->leftJoin('b.pays', 'bp')
            ->orderBy('t.name', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
