<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RecapEmailBatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecapEmailBatch>
 */
class RecapEmailBatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecapEmailBatch::class);
    }

    public function findLatestSentAt(): ?\DateTimeImmutable
    {
        $row = $this->createQueryBuilder('b')
            ->select('b.sentAt')
            ->andWhere('b.dryRun = false')
            ->orderBy('b.sentAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!\is_array($row) || !isset($row['sentAt'])) {
            return null;
        }

        $sentAt = $row['sentAt'];

        return $sentAt instanceof \DateTimeImmutable ? $sentAt : null;
    }

    public function findLatestPeriodEnd(): ?\DateTimeImmutable
    {
        $batch = $this->createQueryBuilder('b')
            ->andWhere('b.dryRun = false')
            ->orderBy('b.sentAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $batch instanceof RecapEmailBatch ? $batch->getPeriodEnd() : null;
    }
}
