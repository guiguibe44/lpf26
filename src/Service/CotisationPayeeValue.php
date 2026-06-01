<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Lecture cohérente de cotisationPayee (bool Doctrine, TINYINT 0/1 en base, formulaires).
 */
final class CotisationPayeeValue
{
    public static function isPaid(mixed $value): bool
    {
        return true === $value || 1 === $value || '1' === $value;
    }

    public static function becamePaid(mixed $oldValue, mixed $newValue): bool
    {
        return !self::isPaid($oldValue) && self::isPaid($newValue);
    }
}
