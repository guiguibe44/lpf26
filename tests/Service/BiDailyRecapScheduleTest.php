<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\BiDailyRecapSchedule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BiDailyRecapScheduleTest extends TestCase
{
    private BiDailyRecapSchedule $schedule;

    protected function setUp(): void
    {
        $this->schedule = new BiDailyRecapSchedule();
    }

    #[DataProvider('sendWindowProvider')]
    public function testIsSendWindowOpen(string $parisDateTime, bool $expected): void
    {
        $now = new \DateTimeImmutable($parisDateTime, new \DateTimeZone(BiDailyRecapSchedule::TIMEZONE));

        self::assertSame($expected, $this->schedule->isSendWindowOpen($now));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function sendWindowProvider(): iterable
    {
        yield 'avant 9h30' => ['2026-06-15 09:00:00', false];
        yield 'à 9h30' => ['2026-06-15 09:30:00', true];
        yield 'après 9h30' => ['2026-06-15 14:00:00', true];
    }

    public function testShouldSendNowRespectsTwoDayInterval(): void
    {
        $tz = new \DateTimeZone(BiDailyRecapSchedule::TIMEZONE);
        $last = new \DateTimeImmutable('2026-06-13 09:30:00', $tz);
        $tooSoon = new \DateTimeImmutable('2026-06-14 10:00:00', $tz);
        $ok = new \DateTimeImmutable('2026-06-15 10:00:00', $tz);

        self::assertFalse($this->schedule->shouldSendNow($tooSoon, $last, false));
        self::assertTrue($this->schedule->shouldSendNow($ok, $last, false));
    }

    public function testShouldSendNowForceBypassesInterval(): void
    {
        $tz = new \DateTimeZone(BiDailyRecapSchedule::TIMEZONE);
        $last = new \DateTimeImmutable('2026-06-15 09:30:00', $tz);
        $now = new \DateTimeImmutable('2026-06-15 09:31:00', $tz);

        self::assertTrue($this->schedule->shouldSendNow($now, $last, true));
    }

    public function testResolvePeriodUsesLastPeriodEnd(): void
    {
        $tz = new \DateTimeZone(BiDailyRecapSchedule::TIMEZONE);
        $now = new \DateTimeImmutable('2026-06-17 10:00:00', $tz);
        $lastEnd = new \DateTimeImmutable('2026-06-15 00:00:00', $tz);

        [$start, $end] = $this->schedule->resolvePeriod($now, $lastEnd);

        self::assertEquals($lastEnd, $start);
        self::assertEquals(new \DateTimeImmutable('2026-06-17 00:00:00', $tz), $end);
    }
}
