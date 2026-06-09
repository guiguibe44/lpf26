<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BadgeAward;
use App\Entity\BadgeDefinition;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BadgeAward>
 */
class BadgeAwardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BadgeAward::class);
    }

    public function hasAwardForUser(BadgeDefinition $badge, User $user): bool
    {
        return null !== $this->findOneBy([
            'badgeDefinition' => $badge,
            'user' => $user,
        ]);
    }

    public function hasAwardForTeam(BadgeDefinition $badge, Team $team): bool
    {
        return null !== $this->findOneBy([
            'badgeDefinition' => $badge,
            'team' => $team,
        ]);
    }

    /**
     * @return list<BadgeAward>
     */
    public function findForUserOrdered(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('b')
            ->join('a.badgeDefinition', 'b')
            ->andWhere('a.user = :user')
            ->andWhere('b.active = true')
            ->setParameter('user', $user)
            ->orderBy('b.category', 'ASC')
            ->addOrderBy('b.sortOrder', 'ASC')
            ->addOrderBy('a.awardedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<BadgeAward>
     */
    public function findForTeamOrdered(Team $team): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('b')
            ->join('a.badgeDefinition', 'b')
            ->andWhere('a.team = :team')
            ->andWhere('b.active = true')
            ->setParameter('team', $team)
            ->orderBy('b.category', 'ASC')
            ->addOrderBy('b.sortOrder', 'ASC')
            ->addOrderBy('a.awardedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<BadgeAward>
     */
    public function findAllForUserAndTeam(User $user, ?Team $team): array
    {
        $qb = $this->createQueryBuilder('a')
            ->addSelect('b')
            ->join('a.badgeDefinition', 'b')
            ->andWhere('b.active = true')
            ->orderBy('a.awardedAt', 'DESC');

        if ($team instanceof Team) {
            $qb->andWhere('a.user = :user OR a.team = :team')
                ->setParameter('user', $user)
                ->setParameter('team', $team);
        } else {
            $qb->andWhere('a.user = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<BadgeAward>
     */
    public function findUnseenForUserAndTeam(User $user, ?Team $team): array
    {
        $qb = $this->createQueryBuilder('a')
            ->addSelect('b')
            ->join('a.badgeDefinition', 'b')
            ->andWhere('a.seenAt IS NULL')
            ->andWhere('b.active = true')
            ->orderBy('a.awardedAt', 'ASC');

        if ($team instanceof Team) {
            $qb->andWhere('a.user = :user OR a.team = :team')
                ->setParameter('user', $user)
                ->setParameter('team', $team);
        } else {
            $qb->andWhere('a.user = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    public function countUnseenForUserAndTeam(User $user, ?Team $team): int
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->join('a.badgeDefinition', 'b')
            ->andWhere('a.seenAt IS NULL')
            ->andWhere('b.active = true');

        if ($team instanceof Team) {
            $qb->andWhere('a.user = :user OR a.team = :team')
                ->setParameter('user', $user)
                ->setParameter('team', $team);
        } else {
            $qb->andWhere('a.user = :user')
                ->setParameter('user', $user);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param list<int> $awardIds
     */
    public function markSeenForUserAndTeam(array $awardIds, User $user, ?Team $team): int
    {
        if ([] === $awardIds) {
            return 0;
        }

        $qb = $this->createQueryBuilder('a')
            ->update()
            ->set('a.seenAt', ':now')
            ->where('a.id IN (:ids)')
            ->andWhere('a.seenAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('ids', $awardIds);

        if ($team instanceof Team) {
            $qb->andWhere('a.user = :user OR a.team = :team')
                ->setParameter('user', $user)
                ->setParameter('team', $team);
        } else {
            $qb->andWhere('a.user = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * @return array<string, BadgeAward> code => award
     */
    public function findEarnedIndexedByCodeForUser(User $user): array
    {
        $indexed = [];
        foreach ($this->findForUserOrdered($user) as $award) {
            $code = $award->getBadgeDefinition()?->getCode();
            if (null !== $code && '' !== $code) {
                $indexed[$code] = $award;
            }
        }

        return $indexed;
    }

    /**
     * @return array<string, BadgeAward> code => award
     */
    public function findEarnedIndexedByCodeForTeam(Team $team): array
    {
        $indexed = [];
        foreach ($this->findForTeamOrdered($team) as $award) {
            $code = $award->getBadgeDefinition()?->getCode();
            if (null !== $code && '' !== $code) {
                $indexed[$code] = $award;
            }
        }

        return $indexed;
    }
}
