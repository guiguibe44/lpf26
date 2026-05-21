<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MainTheme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MainTheme>
 */
class MainThemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MainTheme::class);
    }

    /**
     * @return list<MainTheme>
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.active = :active')
            ->setParameter('active', true)
            ->orderBy('t.sortOrder', 'ASC')
            ->addOrderBy('t.label', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findDefault(): ?MainTheme
    {
        $default = $this->createQueryBuilder('t')
            ->andWhere('t.active = :active')
            ->andWhere('t.isDefault = :isDefault')
            ->setParameter('active', true)
            ->setParameter('isDefault', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($default instanceof MainTheme) {
            return $default;
        }

        $active = $this->findActiveOrdered();

        return $active[0] ?? null;
    }

    public function clearDefaultExcept(?MainTheme $keep): void
    {
        $qb = $this->createQueryBuilder('t')
            ->update()
            ->set('t.isDefault', ':false')
            ->setParameter('false', false);

        if (null !== $keep?->getId()) {
            $qb->andWhere('t.id != :id')->setParameter('id', $keep->getId());
        }

        $qb->getQuery()->execute();
    }
}
