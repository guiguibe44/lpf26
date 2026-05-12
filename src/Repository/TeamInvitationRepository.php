<?php

namespace App\Repository;

use App\Entity\TeamInvitation;
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
}
