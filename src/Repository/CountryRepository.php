<?php

namespace App\Repository;

use App\Entity\Buteur;
use App\Entity\Country;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Country>
 */
class CountryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Country::class);
    }

    /**
     * @return list<Country>
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Pays ayant au moins un joueur synchronisé (sélecteur effectif / buteur).
     *
     * @return list<Country>
     */
    public function findAllWithSquadOrderedByName(): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin(Buteur::class, 'b', 'WITH', 'b.pays = c AND b.actif = true')
            ->groupBy('c.id')
            ->orderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

}
