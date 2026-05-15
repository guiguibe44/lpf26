<?php

namespace App\Tests\Service;

use App\Service\MatchPushReminderPlanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MatchPushReminderPlannerTest extends TestCase
{
    private MatchPushReminderPlanner $planner;

    protected function setUp(): void
    {
        $this->planner = new MatchPushReminderPlanner();
    }

    #[DataProvider('reminderAtProvider')]
    public function testGetReminderAt(string $kickoff, string $expected): void
    {
        $tz = new \DateTimeZone(MatchPushReminderPlanner::TIMEZONE);
        $kickoffAt = new \DateTimeImmutable($kickoff, $tz);

        self::assertSame(
            $expected,
            $this->planner->getReminderAt($kickoffAt)->format('Y-m-d H:i'),
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function reminderAtProvider(): iterable
    {
        yield 'match journée 15h → relance 14h' => ['2026-06-15 15:00:00', '2026-06-15 14:00'];
        yield 'match journée 10h → relance 9h' => ['2026-06-15 10:00:00', '2026-06-15 09:00'];
        yield 'match nuit 3h → relance veille 22h' => ['2026-06-16 03:00:00', '2026-06-15 22:00'];
        yield 'match nuit 9h59 → relance veille 22h' => ['2026-06-16 09:59:00', '2026-06-15 22:00'];
    }

    public function testIsReminderDueBeforeKickoff(): void
    {
        $tz = new \DateTimeZone(MatchPushReminderPlanner::TIMEZONE);
        $kickoff = new \DateTimeImmutable('2026-06-15 15:00:00', $tz);

        self::assertFalse($this->planner->isReminderDue($kickoff, new \DateTimeImmutable('2026-06-15 13:59:00', $tz)));
        self::assertTrue($this->planner->isReminderDue($kickoff, new \DateTimeImmutable('2026-06-15 14:00:00', $tz)));
        self::assertFalse($this->planner->isReminderDue($kickoff, new \DateTimeImmutable('2026-06-15 15:00:00', $tz)));
    }
}
