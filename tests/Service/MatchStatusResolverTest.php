<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\GameMatch;
use App\Service\MatchStatusResolver;
use PHPUnit\Framework\TestCase;

final class MatchStatusResolverTest extends TestCase
{
    private MatchStatusResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new MatchStatusResolver();
    }

    public function testLiveMatchIsStartedButNotFinished(): void
    {
        $match = (new GameMatch())
            ->setStatut('LIVE')
            ->setDateHeure(new \DateTimeImmutable('-10 minutes'))
            ->setScoreDomicile(1)
            ->setScoreExterieur(0);

        self::assertTrue($this->resolver->isMatchStarted($match));
        self::assertFalse($this->resolver->isMatchFinished($match));
        self::assertTrue($this->resolver->isMatchLive($match));
    }

    public function testFinishedMatchIsNotLive(): void
    {
        $match = (new GameMatch())
            ->setStatut('FINISHED')
            ->setDateHeure(new \DateTimeImmutable('-2 hours'))
            ->setScoreDomicile(2)
            ->setScoreExterieur(1);

        self::assertTrue($this->resolver->isMatchFinished($match));
        self::assertFalse($this->resolver->isMatchLive($match));
    }

    public function testScheduledPastKickoffWithScoreIsLiveWithoutLiveStatus(): void
    {
        $now = new \DateTimeImmutable('2026-05-16 12:00:00');
        $match = (new GameMatch())
            ->setStatut('SCHEDULED')
            ->setDateHeure(new \DateTimeImmutable('2026-05-16 10:00:00'))
            ->setScoreDomicile(1)
            ->setScoreExterieur(0);

        self::assertFalse($this->resolver->isMatchFinished($match, $now));
        self::assertTrue($this->resolver->isMatchLive($match, $now));
    }
}
