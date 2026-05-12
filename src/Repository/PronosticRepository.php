<?php

namespace App\Repository;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pronostic>
 */
class PronosticRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pronostic::class);
    }

    /**
     * @return list<array{email:string,totalPoints:float,pronosticsCount:int}>
     */
    public function findRankingSummary(int $limit = 5): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('u.email AS email')
            ->addSelect('COALESCE(SUM(p.points), 0) AS totalPoints')
            ->addSelect('COUNT(p.id) AS pronosticsCount')
            ->join('p.joueur', 'u')
            ->groupBy('u.id')
            ->orderBy('totalPoints', 'DESC')
            ->addOrderBy('pronosticsCount', 'DESC')
            ->addOrderBy('u.email', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'email' => (string) ($row['email'] ?? ''),
            'totalPoints' => (float) round((float) ($row['totalPoints'] ?? 0)),
            'pronosticsCount' => (int) ($row['pronosticsCount'] ?? 0),
        ], $rows);
    }

    /**
     * @return list<Pronostic>
     */
    public function findByMatchWithTeamMembers(GameMatch $match): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('u')
            ->join('p.joueur', 'u')
            ->andWhere('p.match = :match')
            ->setParameter('match', $match)
            ->orderBy('p.points', 'DESC')
            ->addOrderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<GameMatch> $matches
     *
     * @return array<int,Pronostic>
     */
    public function findIndexedByPlayerAndMatches(User $player, array $matches): array
    {
        if ([] === $matches) {
            return [];
        }

        $pronostics = $this->createQueryBuilder('p')
            ->andWhere('p.joueur = :player')
            ->andWhere('p.match IN (:matches)')
            ->setParameter('player', $player)
            ->setParameter('matches', $matches)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($pronostics as $pronostic) {
            $matchId = $pronostic->getMatch()?->getId();
            if (null !== $matchId) {
                $indexed[$matchId] = $pronostic;
            }
        }

        return $indexed;
    }

    /**
     * @param list<GameMatch> $matches
     * @param list<int>       $partnerIds
     *
     * @return array<int,list<Pronostic>>
     */
    public function findIndexedByPlayersAndMatches(array $partnerIds, array $matches): array
    {
        if ([] === $partnerIds || [] === $matches) {
            return [];
        }

        $pronostics = $this->createQueryBuilder('p')
            ->addSelect('u')
            ->join('p.joueur', 'u')
            ->andWhere('IDENTITY(p.joueur) IN (:partnerIds)')
            ->andWhere('p.match IN (:matches)')
            ->setParameter('partnerIds', $partnerIds)
            ->setParameter('matches', $matches)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($pronostics as $pronostic) {
            $matchId = $pronostic->getMatch()?->getId();
            if (null !== $matchId) {
                $indexed[$matchId][] = $pronostic;
            }
        }

        return $indexed;
    }

    /**
     * @return list<Pronostic>
     */
    public function findScoredPronosticsWithTeamMembers(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.points IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * Pronostics des joueurs indiqués sur des matchs déjà terminés (scores renseignés).
     *
     * @param list<int> $userIds
     *
     * @return list<Pronostic>
     */
    public function findForPlayersOnPlayedMatches(array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->addSelect('m', 'hd', 'aw')
            ->join('p.match', 'm')
            ->join('m.paysDomicile', 'hd')
            ->join('m.paysExterieur', 'aw')
            ->andWhere('IDENTITY(p.joueur) IN (:ids)')
            ->andWhere('m.scoreDomicile IS NOT NULL')
            ->andWhere('m.scoreExterieur IS NOT NULL')
            ->setParameter('ids', $userIds)
            ->orderBy('m.dateHeure', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Pronostics des membres de l'équipe sur des matchs déjà joués (scores renseignés).
     *
     * @return list<Pronostic>
     */
    public function findForTeamMembersOnPlayedMatches(Team $team): array
    {
        $ids = [];
        foreach ($team->getMembers() as $member) {
            $id = $member->getPlayer()?->getId();
            if (null !== $id) {
                $ids[] = (int) $id;
            }
        }

        return $this->findForPlayersOnPlayedMatches(array_values(array_unique($ids)));
    }
}
