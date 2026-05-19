<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\NotificationPreference;

final class UserNotificationPreferenceService
{
    /**
     * @return array<string, bool>
     */
    public function getResolvedPreferences(User $user): array
    {
        $stored = $user->getNotificationPreferences() ?? [];
        $resolved = [];

        foreach (NotificationPreference::cases() as $preference) {
            $key = $preference->value;
            $resolved[$key] = \array_key_exists($key, $stored)
                ? (bool) $stored[$key]
                : $preference->defaultEnabled();
        }

        return $resolved;
    }

    public function isEnabled(User $user, NotificationPreference $preference): bool
    {
        $resolved = $this->getResolvedPreferences($user);

        return $resolved[$preference->value] ?? $preference->defaultEnabled();
    }

    /**
     * @param array<string, bool> $submitted
     */
    public function applyToUser(User $user, array $submitted): void
    {
        $normalized = [];
        foreach (NotificationPreference::cases() as $preference) {
            $normalized[$preference->value] = (bool) ($submitted[$preference->value] ?? false);
        }

        $user->setNotificationPreferences($normalized);
    }

    public function hasAnyEmailEnabled(User $user): bool
    {
        foreach (NotificationPreference::cases() as $preference) {
            if ('email' === $preference->channel() && $this->isEnabled($user, $preference)) {
                return true;
            }
        }

        return false;
    }
}
