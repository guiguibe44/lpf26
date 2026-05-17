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
            ->addSelect('j', 't', 'tt')
            ->innerJoin('u.joker', 'j')
            ->innerJoin('u.team', 't')
            ->leftJoin('u.targetTeam', 'tt')
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
            ->addSelect('j', 'm', 'tt')
            ->innerJoin('u.joker', 'j')
            ->innerJoin('u.match', 'm')
            ->leftJoin('u.targetTeam', 'tt')
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

    /**
     * Équipe poseuse => équipe ciblée (joker pique de points uniquement).
     *
     * @return array<int, int>
     */
    public function findPiquePointsTargetsByTeamForMatch(GameMatch $match): array
    {
        $map = [];
        foreach ($this->findByMatch($match) as $usage) {
            if (!$usage instanceof TeamJokerUsage) {
                continue;
            }

            $code = $usage->getJoker()?->getCode();
            if (Joker::CODE_PIQUE_POINTS !== $code) {
                continue;
            }

            $thiefId = $usage->getTeam()?->getId();
            $victimId = $usage->getTargetTeam()?->getId();
            if (null === $thiefId || null === $victimId || (int) $thiefId === (int) $victimId) {
                continue;
            }

            $map[(int) $thiefId] = (int) $victimId;
        }

        return $map;
    }

    /**
     * @return list<int>
     */
    public function findInvertButeurMatchIdsForTargetTeam(Team $targetTeam): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('IDENTITY(u.match) AS matchId')
            ->innerJoin('u.joker', 'j')
            ->andWhere('u.targetTeam = :target')
            ->andWhere('j.code = :code')
            ->setParameter('target', $targetTeam)
            ->setParameter('code', Joker::CODE_INVERSE_BUTEUR)
            ->getQuery()
            ->getScalarResult();

        $matchIds = [];
        foreach ($rows as $row) {
            $matchId = (int) ($row['matchId'] ?? 0);
            if ($matchId > 0) {
                $matchIds[] = $matchId;
            }
        }

        return $matchIds;
    }

    public function teamIsTargetOfInvertButeurOnMatch(Team $targetTeam, GameMatch $match): bool
    {
        $count = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->innerJoin('u.joker', 'j')
            ->andWhere('u.targetTeam = :target')
            ->andWhere('u.match = :match')
            ->andWhere('j.code = :code')
            ->setParameter('target', $targetTeam)
            ->setParameter('match', $match)
            ->setParameter('code', Joker::CODE_INVERSE_BUTEUR)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @return array<int, true>
     */
    public function findInverseScoreTargetTeamIdsForMatch(GameMatch $match): array
    {
        $ids = [];
        foreach ($this->findByMatch($match) as $usage) {
            if (Joker::CODE_INVERSE_SCORE !== $usage->getJoker()?->getCode()) {
                continue;
            }

            $targetId = $usage->getTargetTeam()?->getId();
            if (null !== $targetId) {
                $ids[(int) $targetId] = true;
            }
        }

        return $ids;
    }
}
