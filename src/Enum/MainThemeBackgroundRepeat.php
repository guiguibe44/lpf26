<?php

declare(strict_types=1);

namespace App\Enum;

enum MainThemeBackgroundRepeat: string
{
    case NoRepeat = 'no-repeat';
    case Repeat = 'repeat';
    case RepeatX = 'repeat-x';
    case RepeatY = 'repeat-y';

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

    public function label(): string
    {
        return match ($this) {
            self::NoRepeat => 'Pas de répétition',
            self::Repeat => 'Répéter',
            self::RepeatX => 'Répéter horizontalement',
            self::RepeatY => 'Répéter verticalement',
        };
    }
}
