<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;

final class MatchdayKey
{
    public static function fromMatch(GameMatch $match): ?string
    {
        $dateHeure = $match->getDateHeure();
        if (!$dateHeure instanceof \DateTimeImmutable) {
            return null;
        }

        return $dateHeure->format('Y-m-d');
    }

    public static function dayBounds(string $dayKey): ?array
    {
        $start = \DateTimeImmutable::createFromFormat('Y-m-d', $dayKey);
        if (false === $start) {
            return null;
        }

        $start = $start->setTime(0, 0, 0);

        return [
            'start' => $start,
            'end' => $start->modify('+1 day'),
        ];
    }
}
