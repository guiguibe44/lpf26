<?php

namespace App\Repository;

use App\Entity\PushNotificationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushNotificationLog>
 */
class PushNotificationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushNotificationLog::class);
    }

    /**
     * @return list<PushNotificationLog>
     */
    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('l')
            ->leftJoin('l.sentBy', 'u')
            ->addSelect('u')
            ->orderBy('l.sentAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
