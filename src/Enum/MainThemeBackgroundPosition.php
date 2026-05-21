<?php

declare(strict_types=1);

namespace App\Enum;

enum MainThemeBackgroundPosition: string
{
    case Center = 'center center';
    case Top = 'top center';
    case Bottom = 'bottom center';
    case Left = 'center left';
    case Right = 'center right';
    case TopLeft = 'top left';
    case TopRight = 'top right';
    case BottomLeft = 'bottom left';
    case BottomRight = 'bottom right';

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
            self::Center => 'Centré',
            self::Top => 'Haut',
            self::Bottom => 'Bas',
            self::Left => 'Gauche',
            self::Right => 'Droite',
            self::TopLeft => 'Haut gauche',
            self::TopRight => 'Haut droite',
            self::BottomLeft => 'Bas gauche',
            self::BottomRight => 'Bas droite',
        };
    }
}
