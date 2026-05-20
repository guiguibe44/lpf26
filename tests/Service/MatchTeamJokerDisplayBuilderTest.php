<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Repository\JokerRepository;
use App\Repository\TeamJokerUsageRepository;
use App\Repository\TeamRepository;
use App\Service\JokerDefenseService;
use App\Service\MatchTeamJokerDisplayBuilder;
use PHPUnit\Framework\TestCase;

final class MatchTeamJokerDisplayBuilderTest extends TestCase
{
    public function testIncomingOffensiveWithoutAttackerOnTarget(): void
    {
        $match = new GameMatch();
        $attacker = $this->createTeamMock(3, 'C');
        $victim = $this->createTeamMock(2, 'B');

        $pique = (new Joker())->setCode(Joker::CODE_PIQUE_POINTS)->setName('Pique')->setTitle('Pique');
        $usage = (new TeamJokerUsage())
            ->setTeam($attacker)
            ->setTargetTeam($victim)
            ->setMatch($match)
            ->setJoker($pique);

        $usageRepo = $this->createMock(TeamJokerUsageRepository::class);
        $usageRepo->method('findByMatch')->willReturn([$usage]);
        $usageRepo->method('findCollecteTeamIdsForMatch')->willReturn([]);
        $usageRepo->method('findProtectedTeamIdsForMatchdayOfMatch')->willReturn([]);

        $teamRepo = $this->createMock(TeamRepository::class);
        $teamRepo->method('findTeamIdsWithFavoriteCountryInGroupMatch')->willReturn([]);

        $jokerRepo = $this->createMock(JokerRepository::class);

        $builder = new MatchTeamJokerDisplayBuilder(
            $usageRepo,
            $jokerRepo,
            new JokerDefenseService($usageRepo, $teamRepo),
        );
        $map = $builder->buildByTeamIdForMatch($match, [2, 3]);

        self::assertCount(1, $map[2]);
        self::assertSame('incoming', $map[2][0]['kind']);
        self::assertSame(Joker::CODE_PIQUE_POINTS, $map[2][0]['code']);
        self::assertArrayNotHasKey(3, $map[2] ?? []);
        self::assertCount(1, $map[3]);
        self::assertSame('own', $map[3][0]['kind']);
    }

    public function testEspionBadgesOnePerUsageOnMatch(): void
    {
        $match = new GameMatch();
        $teamA = $this->createTeamMock(1, 'A');
        $teamB = $this->createTeamMock(2, 'B');
        $espion = (new Joker())->setCode(Joker::CODE_ESPION)->setName('Espion')->setTitle('Espion');

        $usageA = (new TeamJokerUsage())->setTeam($teamA)->setMatch($match)->setJoker($espion);
        $usageB = (new TeamJokerUsage())->setTeam($teamB)->setMatch($match)->setJoker($espion);

        $usageRepo = $this->createMock(TeamJokerUsageRepository::class);
        $usageRepo->method('findByMatch')->willReturn([$usageA, $usageB]);
        $usageRepo->method('findCollecteTeamIdsForMatch')->willReturn([]);

        $builder = new MatchTeamJokerDisplayBuilder(
            $usageRepo,
            $this->createMock(JokerRepository::class),
            new JokerDefenseService($usageRepo, $this->createMock(TeamRepository::class)),
        );

        $badges = $builder->buildEspionBadgesForMatch($match);
        self::assertCount(2, $badges);
        self::assertSame(Joker::CODE_ESPION, $badges[0]['code']);
        self::assertSame('2 équipes', $badges[0]['label']);
        self::assertSame('2 équipes', $badges[1]['label']);

        $map = $builder->buildByTeamIdForMatch($match, [1, 2]);
        self::assertSame([], $map[1] ?? []);
        self::assertSame([], $map[2] ?? []);
    }

    public function testNeutralizedOffensiveShowsShieldOnTarget(): void
    {
        $match = new GameMatch();
        $attacker = $this->createTeamMock(3, 'C');
        $victim = $this->createTeamMock(2, 'B');

        $pique = (new Joker())->setCode(Joker::CODE_PIQUE_POINTS)->setName('Pique')->setTitle('Pique');
        $usage = (new TeamJokerUsage())
            ->setTeam($attacker)
            ->setTargetTeam($victim)
            ->setMatch($match)
            ->setJoker($pique);

        $usageRepo = $this->createMock(TeamJokerUsageRepository::class);
        $usageRepo->method('findByMatch')->willReturn([$usage]);
        $usageRepo->method('findCollecteTeamIdsForMatch')->willReturn([]);
        $usageRepo->method('findProtectedTeamIdsForMatchdayOfMatch')->willReturn([2 => true]);

        $teamRepo = $this->createMock(TeamRepository::class);
        $teamRepo->method('findTeamIdsWithFavoriteCountryInGroupMatch')->willReturn([]);

        $bouclier = (new Joker())->setCode(Joker::CODE_BOUCLIER)->setName('Bouclier')->setTitle('Bouclier');
        $jokerRepo = $this->createMock(JokerRepository::class);
        $jokerRepo->method('findOneBy')->willReturn($bouclier);

        $builder = new MatchTeamJokerDisplayBuilder(
            $usageRepo,
            $jokerRepo,
            new JokerDefenseService($usageRepo, $teamRepo),
        );
        $map = $builder->buildByTeamIdForMatch($match, [2, 3]);

        self::assertCount(1, $map[2]);
        self::assertSame('shield', $map[2][0]['kind']);
        self::assertSame(Joker::CODE_BOUCLIER, $map[2][0]['code']);
    }

    private function createTeamMock(int $id, string $name): Team
    {
        $team = $this->createMock(Team::class);
        $team->method('getId')->willReturn($id);
        $team->method('getName')->willReturn($name);

        return $team;
    }
}
