<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\User;
use App\Repository\TeamJokerUsageRepository;
use App\Service\PronosticScoreInversionService;
use PHPUnit\Framework\TestCase;

final class PronosticScoreInversionServiceTest extends TestCase
{
    public function testEffectiveScoresSwapHomeAndAway(): void
    {
        $repo = $this->createMock(TeamJokerUsageRepository::class);
        $service = new PronosticScoreInversionService($repo);

        self::assertSame(['home' => 0, 'away' => 3], $service->effectiveScores(3, 0, true));
        self::assertSame(['home' => 1, 'away' => 1], $service->effectiveScores(1, 1, true));
        self::assertSame(['home' => 3, 'away' => 0], $service->effectiveScores(3, 0, false));
    }

    public function testBuildEffectiveScoresMarksInvertedTeamPronos(): void
    {
        $match = new GameMatch();
        $userA = (new User())->setEmail('a@test.local');
        $userB = (new User())->setEmail('b@test.local');
        (new \ReflectionProperty($userA, 'id'))->setValue($userA, 1);
        (new \ReflectionProperty($userB, 'id'))->setValue($userB, 2);

        $pronoA = (new Pronostic())
            ->setMatch($match)
            ->setJoueur($userA)
            ->setScoreDomicile(3)
            ->setScoreExterieur(0);
        (new \ReflectionProperty($pronoA, 'id'))->setValue($pronoA, 10);

        $pronoB = (new Pronostic())
            ->setMatch($match)
            ->setJoueur($userB)
            ->setScoreDomicile(1)
            ->setScoreExterieur(1);
        (new \ReflectionProperty($pronoB, 'id'))->setValue($pronoB, 11);

        $repo = $this->createMock(TeamJokerUsageRepository::class);
        $service = new PronosticScoreInversionService($repo);

        $map = $service->buildEffectiveScoresByPronosticId(
            [$pronoA, $pronoB],
            [1 => 100, 2 => 100],
            [100 => true],
        );

        self::assertSame(0, $map[10]['home']);
        self::assertSame(3, $map[10]['away']);
        self::assertTrue($map[10]['inverted']);
        self::assertSame(1, $map[11]['home']);
        self::assertSame(1, $map[11]['away']);
        self::assertTrue($map[11]['inverted']);
    }
}
