<?php

namespace App\Repository;

use App\Entity\PushSubscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushSubscription>
 */
class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    /**
     * @return PushSubscription[]
     */
    public function findAllForBroadcast(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByEndpoint(string $endpoint): ?PushSubscription
    {
        return $this->findOneBy(['endpoint' => $endpoint]);
    }

    public function deleteByUser(User $user): int
    {
        return $this->createQueryBuilder('p')
            ->delete()
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
