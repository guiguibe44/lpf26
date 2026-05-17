<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Repository\TeamJokerUsageRepository;
use App\Repository\TeamRepository;
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
        self::assertFalse(JokerDefenseService::isOffensiveAgainstTeam(Joker::CODE_EQUIPE_FAVORITE));
    }

    public function testUsageNeutralizedWhenTargetProtectedByBouclier(): void
    {
        $match = (new GameMatch())->setDateHeure(new \DateTimeImmutable('2026-06-15 18:00:00'));
        $victim = new Team();
        (new \ReflectionProperty($victim, 'id'))->setValue($victim, 10);
        (new \ReflectionProperty($match, 'id'))->setValue($match, 1);

        $usage = (new TeamJokerUsage())
            ->setMatch($match)
            ->setTargetTeam($victim)
            ->setJoker((new Joker())->setCode(Joker::CODE_PIQUE_POINTS));

        $usageRepo = $this->createMock(TeamJokerUsageRepository::class);
        $usageRepo->method('findProtectedTeamIdsForMatchdayOfMatch')->with($match)->willReturn([10 => true]);

        $teamRepo = $this->createMock(TeamRepository::class);
        $teamRepo->method('findTeamIdsWithFavoriteCountryInGroupMatch')->with($match)->willReturn([]);

        $service = new JokerDefenseService($usageRepo, $teamRepo);

        self::assertTrue($service->isUsageNeutralized($usage));
        self::assertTrue($service->isTeamProtectedOnMatch($victim, $match));
        self::assertTrue($service->teamHasBouclierOnMatchday($victim, $match));
    }

    public function testTeamProtectedByFavoriteOnGroupMatchOnly(): void
    {
        $france = (new Country())->setNom('France');
        (new \ReflectionProperty($france, 'id'))->setValue($france, 5);

        $match = (new GameMatch())
            ->setDateHeure(new \DateTimeImmutable('2026-06-15 18:00:00'))
            ->setPhase('Group A')
            ->setPaysDomicile($france)
            ->setPaysExterieur((new Country())->setNom('Brésil'));

        $team = (new Team())->setFavoriteCountry($france);
        (new \ReflectionProperty($team, 'id'))->setValue($team, 20);

        $usageRepo = $this->createMock(TeamJokerUsageRepository::class);
        $usageRepo->method('findProtectedTeamIdsForMatchdayOfMatch')->willReturn([]);

        $teamRepo = $this->createMock(TeamRepository::class);
        $teamRepo->method('findTeamIdsWithFavoriteCountryInGroupMatch')->with($match)->willReturn([20]);

        $service = new JokerDefenseService($usageRepo, $teamRepo);

        self::assertTrue($service->isTeamProtectedByFavoriteOnGroupMatch($team, $match));
        self::assertTrue($service->isTeamProtectedOnMatch($team, $match));
        self::assertFalse($service->teamHasBouclierOnMatchday($team, $match));
    }

    public function testFavoriteProtectionDoesNotApplyOutsideGroupPhase(): void
    {
        $france = (new Country())->setNom('France');
        (new \ReflectionProperty($france, 'id'))->setValue($france, 5);

        $match = (new GameMatch())
            ->setDateHeure(new \DateTimeImmutable('2026-07-15 18:00:00'))
            ->setPhase('Round of 16')
            ->setPaysDomicile($france);

        $team = (new Team())->setFavoriteCountry($france);

        $usageRepo = $this->createMock(TeamJokerUsageRepository::class);
        $teamRepo = $this->createMock(TeamRepository::class);
        $teamRepo->method('findTeamIdsWithFavoriteCountryInGroupMatch')->with($match)->willReturn([]);

        $service = new JokerDefenseService($usageRepo, $teamRepo);

        self::assertFalse($service->isTeamProtectedByFavoriteOnGroupMatch($team, $match));
    }
}
