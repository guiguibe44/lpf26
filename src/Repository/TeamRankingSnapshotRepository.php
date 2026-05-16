<?php

namespace App\Repository;

use App\Entity\GameMatch;
use App\Entity\Team;
use App\Entity\TeamRankingSnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamRankingSnapshot>
 */
class TeamRankingSnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamRankingSnapshot::class);
    }

    public function findOneByMatchAndTeam(GameMatch $match, Team $team): ?TeamRankingSnapshot
    {
        return $this->findOneBy([
            'matchRef' => $match,
            'team' => $team,
        ]);
    }

    /**
     * @return list<TeamRankingSnapshot>
     */
    public function findLatestRanking(): array
    {
        $latestMatchRow = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.matchRef) AS matchId')
            ->join('s.matchRef', 'm')
            ->orderBy('m.dateHeure', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $latestMatchId = (int) ($latestMatchRow['matchId'] ?? 0);
        if (0 === $latestMatchId) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->addSelect('t')
            ->addSelect('m')
            ->join('s.team', 't')
            ->join('s.matchRef', 'm')
            ->andWhere('IDENTITY(s.matchRef) = :matchId')
            ->setParameter('matchId', $latestMatchId)
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function deleteForMatchesFromDate(\DateTimeImmutable $date): void
    {
        $snapshots = $this->createQueryBuilder('s')
            ->join('s.matchRef', 'm')
            ->andWhere('m.dateHeure >= :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();

        $entityManager = $this->getEntityManager();
        foreach ($snapshots as $snapshot) {
            $entityManager->remove($snapshot);
        }
    }

    /**
     * Tous les snapshots d'une equipe, ordonnes par date/heure du match de reference puis id.
     *
     * @return list<TeamRankingSnapshot>
     */
    public function findSnapshotsForTeamOrderedByMatch(Team $team): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('m')
            ->join('s.matchRef', 'm')
            ->andWhere('s.team = :team')
            ->setParameter('team', $team)
            ->orderBy('m.dateHeure', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countTeamsForMatch(GameMatch $match): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.matchRef = :match')
            ->setParameter('match', $match)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<TeamRankingSnapshot>
     */
    public function findRankingForMatch(GameMatch $match): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('t')
            ->join('s.team', 't')
            ->andWhere('s.matchRef = :match')
            ->setParameter('match', $match)
            ->orderBy('s.position', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
