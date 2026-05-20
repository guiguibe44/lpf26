<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Paliers visuels pour le total points équipe sur une carte match.
 */
final class TeamMatchPointsTierResolver
{
    public function resolveTier(int $points): string
    {
        if ($points < 0) {
            return 'negative';
        }

        if (0 === $points) {
            return 'zero';
        }

        if ($points < 15) {
            return 'low';
        }

        if ($points < 35) {
            return 'good';
        }

        if ($points < 60) {
            return 'strong';
        }

        return 'high';
    }

    public function resolveTierLabel(int $points): string
    {
        return match ($this->resolveTier($points)) {
            'negative' => 'Points négatifs',
            'zero' => 'Aucun point',
            'low' => 'Peu de points',
            'good' => 'Bons points',
            'strong' => 'Très bons points',
            'high' => 'Excellent total',
            default => 'Points équipe',
        };
    }
}
