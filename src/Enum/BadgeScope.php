<?php

declare(strict_types=1);

namespace App\Enum;

enum BadgeScope: string
{
    case Player = 'player';
    case Team = 'team';

    public function label(): string
    {
        return match ($this) {
            self::Player => 'Joueur',
            self::Team => 'Équipe',
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
