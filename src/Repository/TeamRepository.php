<?php

namespace App\Repository;

use App\Entity\GameMatch;
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

    /**
     * Équipes dont le pays favori joue ce match de poule.
     *
     * @return list<int>
     */
    public function findTeamIdsWithFavoriteCountryInGroupMatch(GameMatch $match): array
    {
        if (!$match->isGroupStageMatch()) {
            return [];
        }

        $countryIds = [];
        foreach ([$match->getPaysDomicile(), $match->getPaysExterieur()] as $country) {
            if (null !== $country?->getId()) {
                $countryIds[(int) $country->getId()] = (int) $country->getId();
            }
        }

        if ([] === $countryIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('t')
            ->select('t.id')
            ->andWhere('t.favoriteCountry IN (:countryIds)')
            ->setParameter('countryIds', array_values($countryIds))
            ->getQuery()
            ->getScalarResult();

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
