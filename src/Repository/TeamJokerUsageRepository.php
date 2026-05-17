<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Service\MatchdayKey;
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
        $protected = $this->findProtectedTeamIdsForMatchdayOfMatch($match);
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

            if (isset($protected[(int) $victimId])) {
                continue;
            }

            $map[(int) $thiefId] = (int) $victimId;
        }

        return $map;
    }

    /**
     * Équipes protégées par un bouclier posé sur un match de la même journée calendaire.
     *
     * @return array<int, true>
     */
    public function findProtectedTeamIdsForMatchdayOfMatch(GameMatch $match): array
    {
        $dayKey = MatchdayKey::fromMatch($match);
        if (null === $dayKey) {
            return [];
        }

        $bounds = MatchdayKey::dayBounds($dayKey);
        if (null === $bounds) {
            return [];
        }

        $rows = $this->createQueryBuilder('u')
            ->select('IDENTITY(u.team) AS teamId')
            ->innerJoin('u.joker', 'j')
            ->innerJoin('u.match', 'm')
            ->andWhere('j.code = :code')
            ->andWhere('m.dateHeure >= :start')
            ->andWhere('m.dateHeure < :end')
            ->setParameter('code', Joker::CODE_BOUCLIER)
            ->setParameter('start', $bounds['start'])
            ->setParameter('end', $bounds['end'])
            ->getQuery()
            ->getScalarResult();

        $ids = [];
        foreach ($rows as $row) {
            $teamId = (int) ($row['teamId'] ?? 0);
            if ($teamId > 0) {
                $ids[$teamId] = true;
            }
        }

        return $ids;
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
            if ($matchId <= 0) {
                continue;
            }

            $usageMatch = $this->getEntityManager()->find(GameMatch::class, $matchId);
            if (!$usageMatch instanceof GameMatch) {
                continue;
            }

            if ($this->isTeamProtectedOnMatchday($targetTeam, $usageMatch)) {
                continue;
            }

            $matchIds[] = $matchId;
        }

        return $matchIds;
    }

    public function teamIsTargetOfInvertButeurOnMatch(Team $targetTeam, GameMatch $match): bool
    {
        if ($this->isTeamProtectedOnMatchday($targetTeam, $match)) {
            return false;
        }

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
        $protected = $this->findProtectedTeamIdsForMatchdayOfMatch($match);
        $ids = [];
        foreach ($this->findByMatch($match) as $usage) {
            if (Joker::CODE_INVERSE_SCORE !== $usage->getJoker()?->getCode()) {
                continue;
            }

            $targetId = $usage->getTargetTeam()?->getId();
            if (null === $targetId || isset($protected[(int) $targetId])) {
                continue;
            }

            $ids[(int) $targetId] = true;
        }

        return $ids;
    }

    public function isTeamProtectedOnMatchday(Team $team, GameMatch $match): bool
    {
        $teamId = $team->getId();
        if (null === $teamId) {
            return false;
        }

        return isset($this->findProtectedTeamIdsForMatchdayOfMatch($match)[(int) $teamId]);
    }

    /**
     * Équipes ayant posé le joker collecte sur ce match (ordre stable).
     *
     * @return list<int>
     */
    public function findCollecteTeamIdsForMatch(GameMatch $match): array
    {
        $ids = [];
        foreach ($this->findByMatch($match) as $usage) {
            if (Joker::CODE_COLLECTE_POINTS !== $usage->getJoker()?->getCode()) {
                continue;
            }

            $teamId = $usage->getTeam()?->getId();
            if (null !== $teamId) {
                $ids[] = (int) $teamId;
            }
        }

        sort($ids);

        return $ids;
    }
}
