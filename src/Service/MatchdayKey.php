<?php

declare(strict_types=1);

namespace App\Service;

use App\DateTime\AppTimezone;
use App\Entity\GameMatch;

final class MatchdayKey
{
    public static function fromMatch(GameMatch $match): ?string
    {
        $dateHeure = $match->getDateHeure();
        if (!$dateHeure instanceof \DateTimeImmutable) {
            return null;
        }

        return AppTimezone::toLocal($dateHeure)->format('Y-m-d');
    }

    /**
     * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable}|null
     */
    public static function dayBounds(string $dayKey): ?array
    {
        $start = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $dayKey.' 00:00:00',
            AppTimezone::zone(),
        );
        if (false === $start) {
            return null;
        }

        return [
            'start' => $start,
            'end' => $start->modify('+1 day'),
        ];
    }

    public static function dayStartForMatch(GameMatch $match): ?\DateTimeImmutable
    {
        $dayKey = self::fromMatch($match);
        if (null === $dayKey) {
            return null;
        }

        return self::dayBounds($dayKey)['start'] ?? null;
    }
}
