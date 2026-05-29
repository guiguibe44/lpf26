<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\TeamMemberRepository;

/**
 * Contexte Twig / Stimulus pour la sélection de buteur par clic sur un joueur.
 *
 * @phpstan-type ButeurPickContext array{
 *     enabled: bool,
 *     current_id: int|null,
 *     current_name: string|null,
 *     disabled_reason: string|null
 * }
 */
final class ButeurPickContextFactory
{
    public function __construct(
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly CompetitionStatus $competitionStatus,
    ) {
    }

    /**
     * @return ButeurPickContext
     */
    public function create(?User $user): array
    {
        if (!$user instanceof User) {
            return $this->disabled(null, 'login');
        }

        if (null === $this->teamMemberRepository->findOneBy(['player' => $user])) {
            return $this->disabled($user, 'team');
        }

        if ($this->competitionStatus->isStarted()) {
            return $this->disabled($user, 'competition');
        }

        if (!$user->isCotisationPayee()) {
            return $this->disabled($user, 'cotisation');
        }

        $buteur = $user->getButeurChoisi();
        $currentName = null;
        if (null !== $buteur) {
            $currentName = trim(sprintf('%s %s', (string) $buteur->getPrenom(), (string) $buteur->getNom()));
        }

        return [
            'enabled' => true,
            'current_id' => null !== $buteur?->getId() ? (int) $buteur->getId() : null,
            'current_name' => '' !== $currentName ? $currentName : null,
            'disabled_reason' => null,
        ];
    }

    /**
     * @return ButeurPickContext
     */
    private function disabled(?User $user, string $reason): array
    {
        $buteur = $user?->getButeurChoisi();
        $currentName = null;
        if (null !== $buteur) {
            $currentName = trim(sprintf('%s %s', (string) $buteur->getPrenom(), (string) $buteur->getNom()));
        }

        return [
            'enabled' => false,
            'current_id' => null !== $buteur?->getId() ? (int) $buteur->getId() : null,
            'current_name' => '' !== $currentName ? $currentName : null,
            'disabled_reason' => $reason,
        ];
    }
}
