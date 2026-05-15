<?php

namespace App\Repository;

use App\Entity\GameMatch;
use App\Entity\MatchReminderLog;
use App\Enum\ReminderTrigger;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MatchReminderLog>
 */
class MatchReminderLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MatchReminderLog::class);
    }

    /**
     * @return list<MatchReminderLog>
     */
    public function findRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('u', 'm', 'sb')
            ->join('l.user', 'u')
            ->leftJoin('l.match', 'm')
            ->leftJoin('l.sentBy', 'sb')
            ->orderBy('l.sentAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<MatchReminderLog>
     */
    public function findForMatch(GameMatch $match, int $limit = 100): array
    {
        return $this->createQueryBuilder('l')
            ->addSelect('u', 'sb')
            ->join('l.user', 'u')
            ->leftJoin('l.sentBy', 'sb')
            ->andWhere('l.match = :match')
            ->setParameter('match', $match)
            ->orderBy('l.sentAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function hasSuccessfulAutoReminder(GameMatch $match, int $userId): bool
    {
        $count = (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.match = :match')
            ->andWhere('IDENTITY(l.user) = :userId')
            ->andWhere('l.trigger = :trigger')
            ->andWhere('l.success = :success')
            ->setParameter('match', $match)
            ->setParameter('userId', $userId)
            ->setParameter('trigger', ReminderTrigger::Auto)
            ->setParameter('success', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
