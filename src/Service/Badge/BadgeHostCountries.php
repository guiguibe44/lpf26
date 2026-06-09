<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Entity\Country;
use App\Entity\GameMatch;

final class BadgeHostCountries
{
    /** @var list<string> */
    private const HOST_NAMES = [
        'canada',
        'mexico',
        'mexique',
        'united states',
        'usa',
        'états-unis',
        'etats-unis',
    ];

    public static function matchInvolvesHost(GameMatch $match): bool
    {
        return self::isHostCountry($match->getPaysDomicile())
            || self::isHostCountry($match->getPaysExterieur());
    }

    public static function isHostCountry(?Country $country): bool
    {
        if (!$country instanceof Country) {
            return false;
        }

        $normalized = mb_strtolower(trim((string) $country->getNom()));

        return \in_array($normalized, self::HOST_NAMES, true);
    }

    public static function hostKey(?Country $country): ?string
    {
        if (!self::isHostCountry($country)) {
            return null;
        }

        $normalized = mb_strtolower(trim((string) $country->getNom()));

        return match (true) {
            str_contains($normalized, 'canada') => 'canada',
            str_contains($normalized, 'mex') => 'mexico',
            default => 'usa',
        };
    }
}
