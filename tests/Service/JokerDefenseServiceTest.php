<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Repository\TeamJokerUsageRepository;
use App\Service\JokerDefenseService;
use PHPUnit\Framework\TestCase;

final class JokerDefenseServiceTest extends TestCase
{
    public function testOffensiveJokerCodes(): void
    {
        self::assertTrue(JokerDefenseService::isOffensiveAgainstTeam(Joker::CODE_PIQUE_POINTS));
        self::assertTrue(JokerDefenseService::isOffensiveAgainstTeam(Joker::CODE_INVERSE_SCORE));
        self::assertFalse(JokerDefenseService::isOffensiveAgainstTeam(Joker::CODE_BOUCLIER));
        self::assertFalse(JokerDefenseService::isOffensiveAgainstTeam(Joker::CODE_DOUBLE_EQUIPE));
    }

    public function testUsageNeutralizedWhenTargetProtected(): void
    {
        $match = (new GameMatch())->setDateHeure(new \DateTimeImmutable('2026-06-15 18:00:00'));
        $victim = new Team();
        (new \ReflectionProperty($victim, 'id'))->setValue($victim, 10);
        (new \ReflectionProperty($match, 'id'))->setValue($match, 1);

        $usage = (new TeamJokerUsage())
            ->setMatch($match)
            ->setTargetTeam($victim)
            ->setJoker((new Joker())->setCode(Joker::CODE_PIQUE_POINTS));

        $repo = $this->createMock(TeamJokerUsageRepository::class);
        $repo->method('findProtectedTeamIdsForMatchdayOfMatch')->with($match)->willReturn([10 => true]);

        $service = new JokerDefenseService($repo);

        self::assertTrue($service->isUsageNeutralized($usage));
        self::assertTrue($service->isTeamProtectedOnMatch($victim, $match));
    }
}
