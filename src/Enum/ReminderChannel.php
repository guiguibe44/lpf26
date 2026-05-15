<?php

namespace App\Enum;

enum ReminderChannel: string
{
    case Push = 'push';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Push => 'Push',
            self::Email => 'E-mail',
        };
    }
}
