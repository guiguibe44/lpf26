<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\GameMatch;
use App\Service\MatchdayKey;
use PHPUnit\Framework\TestCase;

final class MatchdayKeyTest extends TestCase
{
    public function testFromMatchUsesParisCalendarDayForUtcKickoff(): void
    {
        $match = (new GameMatch())
            ->setDateHeure(new \DateTimeImmutable('2026-06-15T22:30:00+00:00'));

        self::assertSame('2026-06-16', MatchdayKey::fromMatch($match));
    }

    public function testDayBoundsCoverParisMidnightInUtc(): void
    {
        $bounds = MatchdayKey::dayBounds('2026-06-16');
        self::assertNotNull($bounds);

        $kickoff = new \DateTimeImmutable('2026-06-15T22:30:00+00:00');
        self::assertGreaterThanOrEqual($bounds['start'], $kickoff);
        self::assertLessThan($bounds['end'], $kickoff);
    }

    public function testSummerKickoffDisplaysAsParisEvening(): void
    {
        $match = (new GameMatch())
            ->setDateHeure(new \DateTimeImmutable('2026-06-15T19:00:00+00:00'));

        self::assertSame('2026-06-15', MatchdayKey::fromMatch($match));
        self::assertSame(
            '21:00',
            $match->getDateHeure()?->setTimezone(new \DateTimeZone('Europe/Paris'))->format('H:i'),
        );
    }
}
