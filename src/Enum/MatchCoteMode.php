<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Mode de calcul des cotes pronostic sur un match.
 */
enum MatchCoteMode: string
{
    /** Cotes 1 / N / 2 (LPF24) : une cote par issue, appliquée selon le barème. */
    case ONE_N_TWO = 'one_n_two';

    /** Cotes par score exact (LPF26 historique) : total pronos ÷ pronos sur le même score. */
    case EXACT_SCORE = 'exact_score';

    public function label(): string
    {
        return match ($this) {
            self::ONE_N_TWO => '1 / N / 2',
            self::EXACT_SCORE => 'score exact',
        };
    }

    public static function fromConfig(string $value): self
    {
        return match (strtolower(trim($value))) {
            'exact_score', 'exact', 'lpf26' => self::EXACT_SCORE,
            default => self::ONE_N_TWO,
        };
    }
}
