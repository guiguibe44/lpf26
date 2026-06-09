<?php

declare(strict_types=1);

namespace App\Enum;

enum BadgeCategory: string
{
    case Pronostic = 'pronostic';
    case Resultats = 'resultats';
    case BigBalls = 'bigballs';
    case Jokers = 'jokers';
    case Classement = 'classement';
    case Vendee = 'vendee';
    case Competition = 'competition';

    public function label(): string
    {
        return match ($this) {
            self::Pronostic => 'Pronostics',
            self::Resultats => 'Résultats & points',
            self::BigBalls => 'BigBalls',
            self::Jokers => 'Jokers',
            self::Classement => 'Classement',
            self::Vendee => 'Vendée',
            self::Competition => 'Compétition / Mondial',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }
}
