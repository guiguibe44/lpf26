<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Security\SuperAdminAuthorization;

/**
 * Feature flag badges : désactivé par défaut (BADGES_ENABLED=false).
 * BADGES_ADMIN_ONLY=true : affichage et attribution réservés aux ROLE_ADMIN (rollout prod).
 */
final class BadgeFeature
{
    public function __construct(
        private readonly bool $badgesEnabled,
        private readonly bool $badgesIronicEnabled,
        private readonly bool $badgesAdminOnly,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->badgesEnabled;
    }

    public function isAdminOnly(): bool
    {
        return $this->badgesAdminOnly;
    }

    public function isIronicEnabled(): bool
    {
        return $this->badgesIronicEnabled;
    }

    /**
     * Badges visibles et notifiables pour ce joueur (onglet compte, animation, API).
     */
    public function isActiveForUser(User $user): bool
    {
        if (!$this->badgesEnabled) {
            return false;
        }

        if (!$this->badgesAdminOnly) {
            return true;
        }

        return $user->isAdministrator();
    }

    /**
     * Aperçu design sur « Mon compte » pour le super-admin quand les badges publics sont off.
     */
    public function isAccountDesignPreview(User $user): bool
    {
        if ($this->isActiveForUser($user)) {
            return false;
        }

        return SuperAdminAuthorization::isSuperAdminEmail((string) $user->getEmail());
    }

    public function isBadgeEligible(bool $ironic): bool
    {
        if (!$this->badgesEnabled) {
            return false;
        }

        if ($ironic && !$this->badgesIronicEnabled) {
            return false;
        }

        return true;
    }

    public function getPublicStatusLabel(): string
    {
        if (!$this->badgesEnabled) {
            return 'désactivée (BADGES_ENABLED=false) — catalogue admin disponible';
        }

        if ($this->badgesAdminOnly) {
            return 'active pour les admins uniquement (BADGES_ADMIN_ONLY=true)';
        }

        return 'active pour tous les joueurs (BADGES_ENABLED=true)';
    }
}
