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
            ->andWhere('b.actif = :actif')
            ->setParameter('actif', true)
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
     * Joueurs d'un pays, triés par numéro de maillot puis nom (pour la compo terrain).
     *
     * @return list<Buteur>
     */
    /**
     * Joueurs d'un pays ayant un identifiant API-Sports (pour enrichissement profil).
     *
     * @return list<Buteur>
     */
    public function findByCountryWithApiPlayerId(int $countryId): array
    {
        return $this->createQueryBuilder('b')
            ->addSelect('p')
            ->join('b.pays', 'p')
            ->andWhere('p.id = :countryId')
            ->andWhere('b.apiSportsPlayerId IS NOT NULL')
            ->setParameter('countryId', $countryId)
            ->orderBy('b.nom', 'ASC')
            ->addOrderBy('b.prenom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByCountryForPitch(int $countryId): array
    {
        return $this->createQueryBuilder('b')
            ->addSelect('p')
            ->join('b.pays', 'p')
            ->andWhere('p.id = :countryId')
            ->andWhere('b.actif = :actif')
            ->setParameter('countryId', $countryId)
            ->setParameter('actif', true)
            ->orderBy('b.numero', 'ASC')
            ->addOrderBy('b.nom', 'ASC')
            ->addOrderBy('b.prenom', 'ASC')
            ->getQuery()
            ->getResult();
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
            ->join('b.pays', 'p')
            ->andWhere('b.actif = :actif')
            ->setParameter('actif', true);

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
