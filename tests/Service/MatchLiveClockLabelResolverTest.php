<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\GameMatch;
use App\Service\MatchLiveClockLabelResolver;
use PHPUnit\Framework\TestCase;

final class MatchLiveClockLabelResolverTest extends TestCase
{
    public function testEnCoursWithoutApiSync(): void
    {
        $match = new GameMatch();
        $match->setApiFootballSyncEnabled(false);
        $match->setLiveElapsedMinute(67);

        $resolver = new MatchLiveClockLabelResolver();

        self::assertSame('En cours', $resolver->resolve($match));
    }

    public function testMinuteWithApiSync(): void
    {
        $match = new GameMatch();
        $match->setApiFootballSyncEnabled(true);
        $match->setApiFootballFixtureId(99);
        $match->setLiveElapsedMinute(67);

        $resolver = new MatchLiveClockLabelResolver();

        self::assertSame("67'", $resolver->resolve($match));
    }

    public function testEnCoursWhenApiSyncButNoMinuteYet(): void
    {
        $match = new GameMatch();
        $match->setApiFootballSyncEnabled(true);
        $match->setApiFootballFixtureId(99);
        $match->setLiveElapsedMinute(null);

        $resolver = new MatchLiveClockLabelResolver();

        self::assertSame('En cours', $resolver->resolve($match));
    }
}
