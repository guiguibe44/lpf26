<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Issue 1 / N / 2 déduite d'un score domicile–extérieur.
 */
final class MatchOutcomeResolver
{
    public const OUTCOME_HOME = 'HOME';

    public const OUTCOME_DRAW = 'DRAW';

    public const OUTCOME_AWAY = 'AWAY';

    public function resolve(int $scoreHome, int $scoreAway): string
    {
        if ($scoreHome > $scoreAway) {
            return self::OUTCOME_HOME;
        }

        if ($scoreHome < $scoreAway) {
            return self::OUTCOME_AWAY;
        }

        return self::OUTCOME_DRAW;
    }

    public function label(string $outcome): string
    {
        return match ($outcome) {
            self::OUTCOME_HOME => '1',
            self::OUTCOME_DRAW => 'N',
            self::OUTCOME_AWAY => '2',
            default => '?',
        };
    }
}
