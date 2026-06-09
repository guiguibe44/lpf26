<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Entity\BadgeAward;
use App\Entity\BadgeDefinition;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\TeamMemberRepository;
use App\Repository\BadgeAwardRepository;
use App\Service\BadgeFeature;
use Doctrine\ORM\EntityManagerInterface;

final class BadgeAwardGranter
{
    public function __construct(
        private readonly BadgeAwardRepository $badgeAwardRepository,
        private readonly BadgeFeature $badgeFeature,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function grantToUser(BadgeDefinition $badge, User $user, ?array $metadata = null): bool
    {
        if (!$this->canGrant($badge) || !$this->badgeFeature->isActiveForUser($user)) {
            return false;
        }

        if ($this->badgeAwardRepository->hasAwardForUser($badge, $user)) {
            return false;
        }

        $award = (new BadgeAward())
            ->setBadgeDefinition($badge)
            ->setUser($user)
            ->setMetadata($metadata);

        $this->entityManager->persist($award);

        return true;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function grantToTeam(BadgeDefinition $badge, Team $team, ?array $metadata = null): bool
    {
        if (!$this->canGrant($badge) || !$this->isTeamEligible($team)) {
            return false;
        }

        if ($this->badgeAwardRepository->hasAwardForTeam($badge, $team)) {
            return false;
        }

        $award = (new BadgeAward())
            ->setBadgeDefinition($badge)
            ->setTeam($team)
            ->setMetadata($metadata);

        $this->entityManager->persist($award);

        return true;
    }

    private function isTeamEligible(Team $team): bool
    {
        if (!$this->badgeFeature->isEnabled()) {
            return false;
        }

        if (!$this->badgeFeature->isAdminOnly()) {
            return true;
        }

        foreach ($this->teamMemberRepository->findBy(['team' => $team]) as $member) {
            $player = $member->getPlayer();
            if ($player instanceof User && $this->badgeFeature->isActiveForUser($player)) {
                return true;
            }
        }

        return false;
    }

    private function canGrant(BadgeDefinition $badge): bool
    {
        return $badge->isActive() && $this->badgeFeature->isBadgeEligible($badge->isIronic());
    }
}
