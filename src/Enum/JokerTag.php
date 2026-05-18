<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Catégorie affichée sur les cartes joker (guide, compte, tiroir).
 */
enum JokerTag: string
{
    case Attaque = 'attaque';
    case Defense = 'defense';
    case Bonus = 'bonus';
    case Points = 'points';
    case Intel = 'intel';
    case Buteur = 'buteur';
    case Autre = 'autre';

    /**
     * @return array<string, string> valeur => libellé (admin + front)
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }

    public function label(): string
    {
        return match ($this) {
            self::Attaque => 'Attaque',
            self::Defense => 'Défense',
            self::Bonus => 'Bonus',
            self::Points => 'Points équipe',
            self::Intel => 'Renseignement',
            self::Buteur => 'Buteurs',
            self::Autre => 'Autre',
        };
    }

    public static function labelFor(?string $value): string
    {
        if (null === $value || '' === $value) {
            return self::Autre->label();
        }

        return self::tryFrom($value)?->label() ?? $value;
    }

    /** Anciennes valeurs « category » du guide (rétrocompat CSS). */
    public static function cssClassFor(?string $value): string
    {
        return match ($value) {
            self::Attaque->value => 'attaque',
            self::Defense->value => 'defense',
            self::Bonus->value => 'bonus',
            self::Points->value => 'points',
            self::Intel->value => 'intel',
            self::Buteur->value => 'buteur',
            'offensive' => 'attaque',
            default => 'autre',
        };
    }
}
