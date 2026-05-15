<?php

namespace App\Enum;

enum AdminRecipientScope: string
{
    case All = 'all';
    case Selection = 'selection';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Tous les joueurs (cotisation payée)',
            self::Selection => 'Sélection de joueurs',
        };
    }
}
