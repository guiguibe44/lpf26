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

    /**
     * Buts groupés par identifiant de match, triés par minute.
     *
     * @param list<int> $matchIds
     *
     * @return array<int, list<array{buteur_id: int, name: string, minute: ?int, points: int}>>
     */
    public function findGoalRowsIndexedByMatchIds(array $matchIds): array
    {
        if ([] === $matchIds) {
            return [];
        }

        /** @var list<But> $buts */
        $buts = $this->createQueryBuilder('b')
            ->addSelect('bu', 'm')
            ->join('b.buteur', 'bu')
            ->join('b.matchRef', 'm')
            ->andWhere('m.id IN (:matchIds)')
            ->setParameter('matchIds', $matchIds)
            ->orderBy('b.minute', 'ASC')
            ->addOrderBy('b.id', 'ASC')
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($buts as $but) {
            $match = $but->getMatchRef();
            $buteur = $but->getButeur();
            if (null === $match?->getId() || null === $buteur) {
                continue;
            }

            $matchId = (int) $match->getId();
            $indexed[$matchId] ??= [];
            $indexed[$matchId][] = [
                'buteur_id' => (int) $buteur->getId(),
                'name' => trim(sprintf('%s %s', (string) $buteur->getPrenom(), (string) $buteur->getNom())),
                'minute' => $but->getMinute(),
                'points' => $but->getPointsAttribues(),
            ];
        }

        return $indexed;
    }
}
