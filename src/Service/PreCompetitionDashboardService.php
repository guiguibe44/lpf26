<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Buteur;
use App\Entity\Team;
use App\Entity\TeamInvitation;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use App\Repository\TeamInvitationRepository;
use App\Repository\TeamMemberRepository;

/**
 * Blocs « avant la compétition » sur le tableau de bord.
 */
final class PreCompetitionDashboardService
{
    public function __construct(
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly TeamInvitationRepository $teamInvitationRepository,
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
    ) {
    }

    /**
     * @return array{
     *     account: array{done: bool, checks: list<array{label: string, done: bool}>, hash: string},
     *     buteur: array{done: bool, hash: string, blocked_by_cotisation: bool},
     *     favorite: array{done: bool, hash: string, available: bool},
     *     notifications: array{done: bool, hash: string, configured: bool}
     * }
     */
    public function buildChecklist(User $user): array
    {
        $teamMember = $this->teamMemberRepository->findOneBy(['player' => $user]);
        $team = $teamMember?->getTeam();
        if (null === $team) {
            $team = $this->teamInvitationRepository->findTeamForInviter($user);
        }

        $pendingInvitation = null !== $team
            ? $this->teamInvitationRepository->findPendingForTeam($team)
            : null;

        $teamIsFull = null !== $team && $this->teamMemberRepository->count(['team' => $team]) >= 2;

        return [
            'account' => $this->buildAccountBlock($user, $teamMember, $team, $pendingInvitation, $teamIsFull),
            'buteur' => [
                'done' => $user->getButeurChoisi() instanceof Buteur,
                'hash' => 'buteur',
                'blocked_by_cotisation' => !$user->isCotisationPayee(),
            ],
            'favorite' => $this->buildFavoriteBlock($team),
            'notifications' => $this->buildNotificationsBlock($user),
        ];
    }

    /**
     * @return array{done: bool, checks: list<array{label: string, done: bool}>, hash: string}
     */
    private function buildAccountBlock(
        User $user,
        ?TeamMember $teamMember,
        ?Team $team,
        ?TeamInvitation $pendingInvitation,
        bool $teamIsFull,
    ): array {
        $hasAvatar = null !== $user->getAvatar() && '' !== trim((string) $user->getAvatar());
        $hasProfile = $teamMember instanceof TeamMember;
        $partnerOk = $teamIsFull || $pendingInvitation instanceof TeamInvitation;
        $hasSlogan = $team instanceof Team && '' !== trim((string) $team->getSlogan());
        $hasLogo = $team instanceof Team && '' !== trim((string) $team->getLogo());

        $checks = [
            ['label' => 'Profil joueur (surnom)', 'done' => $hasProfile],
            ['label' => 'Avatar', 'done' => $hasAvatar],
            ['label' => 'Partenaire invité ou inscrit', 'done' => $partnerOk],
            ['label' => 'Slogan d’équipe', 'done' => $hasSlogan],
            ['label' => 'Image d’équipe', 'done' => $hasLogo],
        ];

        if (null === $team) {
            $checks = [
                ['label' => 'Créer votre équipe', 'done' => false],
            ];
        }

        $done = [] === array_filter($checks, static fn (array $c): bool => !$c['done']);

        $hash = null === $team
            ? 'team-setup'
            : ($hasProfile ? 'tab-equipe' : 'tab-compte');

        return [
            'done' => $done,
            'checks' => $checks,
            'hash' => $hash,
        ];
    }

    /**
     * @return array{done: bool, hash: string, available: bool}
     */
    private function buildFavoriteBlock(?Team $team): array
    {
        if (!$team instanceof Team) {
            return [
                'done' => false,
                'hash' => 'tab-equipe',
                'available' => false,
            ];
        }

        return [
            'done' => null !== $team->getFavoriteCountry(),
            'hash' => 'favorite-country',
            'available' => true,
        ];
    }

    /**
     * @return array{done: bool, hash: string, configured: bool}
     */
    private function buildNotificationsBlock(User $user): array
    {
        $userId = $user->getId();
        $hasSubscription = null !== $userId
            && [] !== $this->pushSubscriptionRepository->findByUserIds([(int) $userId]);

        return [
            'done' => $hasSubscription,
            'hash' => 'notifications',
            'configured' => true,
        ];
    }
}
