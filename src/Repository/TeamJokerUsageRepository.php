<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamJokerUsage>
 */
class TeamJokerUsageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamJokerUsage::class);
    }

    /**
     * @return list<TeamJokerUsage>
     */
    public function findByMatch(GameMatch $match): array
    {
        return $this->createQueryBuilder('u')
            ->addSelect('j', 't')
            ->innerJoin('u.joker', 'j')
            ->innerJoin('u.team', 't')
            ->andWhere('u.match = :match')
            ->setParameter('match', $match)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<TeamJokerUsage>
     */
    public function findByTeamOrdered(Team $team): array
    {
        return $this->createQueryBuilder('u')
            ->addSelect('j', 'm')
            ->innerJoin('u.joker', 'j')
            ->innerJoin('u.match', 'm')
            ->andWhere('u.team = :team')
            ->setParameter('team', $team)
            ->orderBy('u.placedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByTeamAndMatch(Team $team, GameMatch $match): ?TeamJokerUsage
    {
        return $this->findOneBy(['team' => $team, 'match' => $match]);
    }

    public function findOneByTeamAndJoker(Team $team, Joker $joker): ?TeamJokerUsage
    {
        return $this->findOneBy(['team' => $team, 'joker' => $joker]);
    }

    /**
     * teamId => joker code
     *
     * @return array<int, string>
     */
    public function findJokerCodesByTeamForMatch(GameMatch $match): array
    {
        $map = [];
        foreach ($this->findByMatch($match) as $usage) {
            $teamId = $usage->getTeam()?->getId();
            $code = $usage->getJoker()?->getCode();
            if (null !== $teamId && null !== $code) {
                $map[(int) $teamId] = $code;
            }
        }

        return $map;
    }
}
