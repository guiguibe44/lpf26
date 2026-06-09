<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Feature flag badges : désactivé par défaut (BADGES_ENABLED=false).
 * L’attribution automatique et l’affichage public consommeront ce service.
 */
final class BadgeFeature
{
    public function __construct(
        private readonly bool $badgesEnabled,
        private readonly bool $badgesIronicEnabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->badgesEnabled;
    }

    public function isIronicEnabled(): bool
    {
        return $this->badgesIronicEnabled;
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
}
