<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AdminQaChecklistNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminQaChecklistNote>
 */
class AdminQaChecklistNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminQaChecklistNote::class);
    }

    public function findOneByItemAndUser(string $itemId, int $userId): ?AdminQaChecklistNote
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.itemId = :itemId')
            ->andWhere('n.author = :userId')
            ->setParameter('itemId', $itemId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<AdminQaChecklistNote>
     */
    public function findByAuthor(int $userId): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.author = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<AdminQaChecklistNote>
     */
    public function findAllWithAuthorOrdered(): array
    {
        return $this->createQueryBuilder('n')
            ->addSelect('u')
            ->innerJoin('n.author', 'u')
            ->andWhere('n.content IS NOT NULL')
            ->andWhere('n.content != :empty')
            ->setParameter('empty', '')
            ->orderBy('n.itemId', 'ASC')
            ->addOrderBy('n.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
