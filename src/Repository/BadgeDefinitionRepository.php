<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BadgeDefinition;
use App\Enum\BadgeCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BadgeDefinition>
 */
class BadgeDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BadgeDefinition::class);
    }

    /**
     * @return list<BadgeDefinition>
     */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.active = :active')
            ->setParameter('active', true)
            ->orderBy('b.category', 'ASC')
            ->addOrderBy('b.sortOrder', 'ASC')
            ->addOrderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByCode(string $code): ?BadgeDefinition
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * @return array<string, BadgeDefinition>
     */
    public function findActiveIndexedByCode(): array
    {
        $indexed = [];
        foreach ($this->findActiveOrdered() as $definition) {
            $code = $definition->getCode();
            if (null !== $code && '' !== $code) {
                $indexed[$code] = $definition;
            }
        }

        return $indexed;
    }

    /**
     * @return list<BadgeDefinition>
     */
    public function findActiveByCategory(BadgeCategory $category): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.active = :active')
            ->andWhere('b.category = :category')
            ->setParameter('active', true)
            ->setParameter('category', $category)
            ->orderBy('b.sortOrder', 'ASC')
            ->addOrderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
