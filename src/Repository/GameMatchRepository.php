<?php

namespace App\Repository;

use App\Entity\GameMatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameMatch>
 */
class GameMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameMatch::class);
    }

    public function findOneByApiFootballFixtureId(int $fixtureId): ?GameMatch
    {
        return $this->findOneBy(['apiFootballFixtureId' => $fixtureId]);
    }

    /**
     * Matchs pour lesquels on peut interroger les événements (buts) via l API.
     *
     * @return list<GameMatch>
     */
    public function findWithFixtureIdForEventsSync(): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.apiFootballFixtureId IS NOT NULL')
            ->andWhere('m.statut IN (:statuses)')
            ->setParameter('statuses', ['FINISHED', 'LIVE'])
            ->orderBy('m.dateHeure', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{date:\DateTimeImmutable,matches:list<GameMatch>}|null
     */
    public function findNextMatchday(): ?array
    {
        $nextMatch = $this->createQueryBuilder('m')
            ->andWhere('m.dateHeure >= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('m.dateHeure', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$nextMatch instanceof GameMatch || null === $nextMatch->getDateHeure()) {
            return null;
        }

        $date = $nextMatch->getDateHeure();
        $start = $date->setTime(0, 0, 0);
        $end = $start->modify('+1 day');

        $matches = $this->createQueryBuilder('m')
            ->addSelect('hd', 'aw')
            ->join('m.paysDomicile', 'hd')
            ->join('m.paysExterieur', 'aw')
            ->andWhere('m.dateHeure >= :start')
            ->andWhere('m.dateHeure < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('m.dateHeure', 'ASC')
            ->getQuery()
            ->getResult();

        return [
            'date' => $start,
            'matches' => $matches,
        ];
    }

    /**
     * Dernier jour calendaire dont tous les matchs sont considérés terminés (même règle que la page Matchs).
     *
     * @return array{date:\DateTimeImmutable,matches:list<GameMatch>}|null
     */
    public function findLatestFinishedMatch(): ?GameMatch
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.scoreDomicile IS NOT NULL')
            ->andWhere('m.scoreExterieur IS NOT NULL')
            ->orderBy('m.dateHeure', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLastCompletedMatchday(?\DateTimeImmutable $now = null): ?array
    {
        $now ??= new \DateTimeImmutable();

        $allMatches = $this->createQueryBuilder('m')
            ->addSelect('hd', 'aw')
            ->join('m.paysDomicile', 'hd')
            ->join('m.paysExterieur', 'aw')
            ->andWhere('m.dateHeure IS NOT NULL')
            ->orderBy('m.dateHeure', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();

        $byDay = [];
        foreach ($allMatches as $match) {
            $dateHeure = $match->getDateHeure();
            if (!$dateHeure instanceof \DateTimeImmutable) {
                continue;
            }
            $byDay[$dateHeure->format('Y-m-d')][] = $match;
        }
        krsort($byDay);

        foreach ($byDay as $matches) {
            $allFinished = true;
            foreach ($matches as $match) {
                if (!$this->isMatchFinishedForListing($match, $now)) {
                    $allFinished = false;
                    break;
                }
            }
            if ($allFinished && [] !== $matches) {
                usort($matches, static function (GameMatch $a, GameMatch $b): int {
                    $da = $a->getDateHeure();
                    $db = $b->getDateHeure();
                    if (!$da instanceof \DateTimeImmutable || !$db instanceof \DateTimeImmutable) {
                        return 0;
                    }

                    return $da <=> $db;
                });
                $firstDate = $matches[0]->getDateHeure();
                if (!$firstDate instanceof \DateTimeImmutable) {
                    return null;
                }
                $start = $firstDate->setTime(0, 0, 0);

                return [
                    'date' => $start,
                    'matches' => $matches,
                ];
            }
        }

        return null;
    }

    private function isMatchFinishedForListing(GameMatch $match, \DateTimeImmutable $now): bool
    {
        $hasFinalScore = null !== $match->getScoreDomicile() && null !== $match->getScoreExterieur();
        $dateHeure = $match->getDateHeure();

        return 'FINISHED' === $match->getStatut()
            || ($hasFinalScore && $dateHeure instanceof \DateTimeImmutable && $dateHeure < $now);
    }

    /**
     * @return list<GameMatch>
     */
    public function findMatchesFromDate(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.dateHeure >= :date')
            ->setParameter('date', $date)
            ->orderBy('m.dateHeure', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Matchs de phase de groupes (libellé « Group … », ex. import FIFA / sync API).
     *
     * @return list<GameMatch>
     */
    /**
     * Matchs à venir, non terminés, relance push pas encore envoyée.
     *
     * @return list<GameMatch>
     */
    /**
     * @return list<GameMatch>
     */
    public function findUpcomingScheduledMatches(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        return $this->createQueryBuilder('m')
            ->addSelect('hd', 'aw')
            ->join('m.paysDomicile', 'hd')
            ->join('m.paysExterieur', 'aw')
            ->andWhere('m.statut = :scheduled')
            ->andWhere('m.dateHeure > :now')
            ->setParameter('scheduled', 'SCHEDULED')
            ->setParameter('now', $now)
            ->orderBy('m.dateHeure', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findScheduledMatchesPendingPushReminder(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        return $this->createQueryBuilder('m')
            ->addSelect('hd', 'aw')
            ->join('m.paysDomicile', 'hd')
            ->join('m.paysExterieur', 'aw')
            ->andWhere('m.statut = :scheduled')
            ->andWhere('m.dateHeure > :now')
            ->andWhere('m.pushReminderSentAt IS NULL')
            ->setParameter('scheduled', 'SCHEDULED')
            ->setParameter('now', $now)
            ->orderBy('m.dateHeure', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findMatchesForGroupStanding(): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('hd', 'aw')
            ->join('m.paysDomicile', 'hd')
            ->join('m.paysExterieur', 'aw')
            ->andWhere('m.phase LIKE :phasePrefix')
            ->setParameter('phasePrefix', 'Group %')
            ->orderBy('m.phase', 'ASC')
            ->addOrderBy('m.dateHeure', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
