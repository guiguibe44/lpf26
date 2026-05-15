<?php

namespace App\Enum;

enum ReminderTrigger: string
{
    case Auto = 'auto';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'Automatique',
            self::Manual => 'Manuel',
        };
    }
}
