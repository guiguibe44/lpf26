<?php

namespace App\Enum;

/**
 * Mode d'envoi pour les relances manuelles ou automatiques.
 */
enum ReminderDeliveryMode: string
{
    /** Push si abonné, sinon e-mail. */
    case PreferPush = 'prefer_push';
    case PushOnly = 'push_only';
    case EmailOnly = 'email_only';

    public function label(): string
    {
        return match ($this) {
            self::PreferPush => 'Push si possible, sinon e-mail',
            self::PushOnly => 'Push uniquement',
            self::EmailOnly => 'E-mail uniquement',
        };
    }
}
