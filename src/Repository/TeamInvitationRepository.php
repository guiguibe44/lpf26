<?php

namespace App\Repository;

use App\Entity\Team;
use App\Entity\TeamInvitation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamInvitation>
 */
class TeamInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamInvitation::class);
    }

    public function findValidByToken(string $token): ?TeamInvitation
    {
        /** @var TeamInvitation|null $invitation */
        $invitation = $this->findOneBy(['token' => $token]);

        if (null === $invitation || $invitation->isAccepted() || $invitation->isExpired()) {
            return null;
        }

        return $invitation;
    }

    public function findTeamForInviter(User $user): ?Team
    {
        /** @var TeamInvitation|null $invitation */
        $invitation = $this->createQueryBuilder('i')
            ->andWhere('i.invitedBy = :user')
            ->setParameter('user', $user)
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $invitation?->getTeam();
    }

    public function findPendingForTeam(Team $team): ?TeamInvitation
    {
        /** @var TeamInvitation|null $invitation */
        $invitation = $this->createQueryBuilder('i')
            ->andWhere('i.team = :team')
            ->andWhere('i.acceptedAt IS NULL')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('team', $team)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $invitation;
    }

    public function findPendingForTeamAndEmail(Team $team, string $email): ?TeamInvitation
    {
        $email = mb_strtolower(trim($email));

        /** @var TeamInvitation|null $invitation */
        $invitation = $this->createQueryBuilder('i')
            ->andWhere('i.team = :team')
            ->andWhere('i.invitedEmail = :email')
            ->andWhere('i.acceptedAt IS NULL')
            ->setParameter('team', $team)
            ->setParameter('email', $email)
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $invitation;
    }
}
