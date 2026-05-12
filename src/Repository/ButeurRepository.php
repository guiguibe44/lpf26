<?php

namespace App\Repository;

use App\Entity\Buteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Buteur>
 */
class ButeurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Buteur::class);
    }

    /**
     * @return list<Buteur>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->addSelect('p')
            ->join('b.pays', 'p')
            ->orderBy('b.nom', 'ASC')
            ->addOrderBy('b.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByApiSportsPlayerId(int $playerId): ?Buteur
    {
        return $this->findOneBy(['apiSportsPlayerId' => $playerId]);
    }

    /**
     * Recherche pour le sélecteur dashboard (nom / prénom / pays, filtre pays optionnel).
     *
     * @return list<Buteur>
     */
    public function searchForPicker(?string $q, ?int $paysId, int $limit = 40): array
    {
        $qb = $this->createQueryBuilder('b')
            ->addSelect('p')
            ->join('b.pays', 'p');

        if (null !== $paysId) {
            $qb->andWhere('p.id = :paysId')->setParameter('paysId', $paysId);
        }

        $trimmed = null !== $q ? trim($q) : '';
        if ('' !== $trimmed) {
            $like = '%'.mb_strtolower($trimmed).'%';
            $qb->andWhere(
                $qb->expr()->orX(
                    'LOWER(b.nom) LIKE :like',
                    'LOWER(b.prenom) LIKE :like',
                    'LOWER(CONCAT(b.prenom, \' \', b.nom)) LIKE :like',
                    'LOWER(CONCAT(b.nom, \' \', b.prenom)) LIKE :like',
                    'LOWER(p.nom) LIKE :like'
                )
            )->setParameter('like', $like);
        } elseif (null === $paysId) {
            return [];
        }

        return $qb
            ->orderBy('b.nom', 'ASC')
            ->addOrderBy('b.prenom', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
