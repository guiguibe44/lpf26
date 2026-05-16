<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\JokerStealPointsService;
use PHPUnit\Framework\TestCase;

final class JokerStealPointsServiceTest extends TestCase
{
    private JokerStealPointsService $service;

    protected function setUp(): void
    {
        $this->service = new JokerStealPointsService(
            $this->createMock(\App\Repository\TeamJokerUsageRepository::class),
        );
    }

    public function testOneWayStealTransfersVictimPointsToThief(): void
    {
        $raw = [1 => 8.0, 2 => 12.0];
        $steal = [1 => 2];

        $final = $this->service->resolveFinalTeamTotals($raw, $steal);

        self::assertSame(20.0, $final[1]);
        self::assertSame(0.0, $final[2]);
    }

    public function testMutualStealInvertsTotals(): void
    {
        $raw = [1 => 8.0, 2 => 12.0];
        $steal = [1 => 2, 2 => 1];

        $final = $this->service->resolveFinalTeamTotals($raw, $steal);

        self::assertSame(12.0, $final[1]);
        self::assertSame(8.0, $final[2]);
    }

    public function testMutualTakesPrecedenceOverOneWayOnSamePair(): void
    {
        $raw = [1 => 5.0, 2 => 10.0];
        $steal = [1 => 2, 2 => 1];

        $final = $this->service->resolveFinalTeamTotals($raw, $steal);

        self::assertSame(10.0, $final[1]);
        self::assertSame(5.0, $final[2]);
    }

    public function testUnaffectedTeamKeepsRawTotal(): void
    {
        $raw = [1 => 6.0, 2 => 4.0, 3 => 9.0];
        $steal = [1 => 2];

        $final = $this->service->resolveFinalTeamTotals($raw, $steal);

        self::assertSame(10.0, $final[1]);
        self::assertSame(0.0, $final[2]);
        self::assertSame(9.0, $final[3]);
    }
}
