<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\Team;
use App\Entity\TeamRankingSnapshot;
use App\Entity\User;
use App\Repository\GameMatchRepository;
use App\Repository\PronosticRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRankingSnapshotRepository;
use App\Repository\TeamRepository;
use App\Service\KdoMatchWinnerService;
use PHPUnit\Framework\TestCase;

final class KdoMatchWinnerServiceTest extends TestCase
{
    public function testReturnsNullWhenNotKdoMatch(): void
    {
        $match = $this->finishedKdoMatch(isKdo: false);
        $service = $this->createService(pronostics: [], previousMatch: null, ranking: []);

        self::assertNull($service->resolveWinner($match));
    }

    public function testTeamWithTwoExactScoresWinsOverTeamWithOne(): void
    {
        $match = $this->finishedKdoMatch();
        $teamA = $this->team(1, 'Alpha');
        $teamB = $this->team(2, 'Bravo');

        $service = $this->createService(
            pronostics: [
                $this->exactPronostic($match, 1, 1, 1),
                $this->exactPronostic($match, 2, 1, 1),
                $this->exactPronostic($match, 3, 0, 0),
            ],
            playerTeamMap: [1 => 1, 2 => 1, 3 => 2],
            previousMatch: null,
            ranking: [],
            teams: [$teamA, $teamB],
        );

        $result = $service->resolveWinner($match);

        self::assertNotNull($result);
        self::assertSame(1, $result->team->getId());
        self::assertSame(2, $result->exactScoresCount);
    }

    public function testTieBreakUsesWorstRankingBeforeMatch(): void
    {
        $match = $this->finishedKdoMatch();
        $previous = (new GameMatch())->setDateHeure(new \DateTimeImmutable('-1 day'));
        $this->setId($previous, 99);

        $teamLow = $this->team(1, 'Bas');
        $teamHigh = $this->team(2, 'Haut');

        $snapLow = (new TeamRankingSnapshot())->setTeam($teamLow)->setPosition(8);
        $snapHigh = (new TeamRankingSnapshot())->setTeam($teamHigh)->setPosition(2);

        $service = $this->createService(
            pronostics: [
                $this->exactPronostic($match, 10, 1, 1),
                $this->exactPronostic($match, 20, 1, 1),
            ],
            playerTeamMap: [10 => 1, 20 => 2],
            previousMatch: $previous,
            ranking: [$snapLow, $snapHigh],
            teams: [$teamLow, $teamHigh],
        );

        $result = $service->resolveWinner($match);

        self::assertNotNull($result);
        self::assertSame(1, $result->team->getId());
        self::assertSame(1, $result->exactScoresCount);
    }

    public function testNoWinnerWhenNoExactScore(): void
    {
        $match = $this->finishedKdoMatch();
        $service = $this->createService(
            pronostics: [
                $this->pronostic($match, 1, 2, 0),
            ],
            playerTeamMap: [1 => 1],
            previousMatch: null,
            ranking: [],
        );

        self::assertNull($service->resolveWinner($match));
    }

    /**
     * @param list<Pronostic>              $pronostics
     * @param array<int, int>              $playerTeamMap
     * @param list<TeamRankingSnapshot>    $ranking
     * @param list<Team>                   $teams
     */
    private function createService(
        array $pronostics,
        array $playerTeamMap = [],
        ?GameMatch $previousMatch = null,
        array $ranking = [],
        array $teams = [],
    ): KdoMatchWinnerService {
        $pronosticRepository = $this->createMock(PronosticRepository::class);
        $pronosticRepository->method('findByMatchWithTeamMembers')->willReturn($pronostics);

        $teamMemberRepository = $this->createMock(TeamMemberRepository::class);
        $teamMemberRepository->method('findPlayerTeamMap')->willReturn($playerTeamMap);

        $teamRepository = $this->createMock(TeamRepository::class);
        $teamRepository->method('find')->willReturnCallback(
            static function (int $id) use ($teams): ?Team {
                foreach ($teams as $team) {
                    if ($team->getId() === $id) {
                        return $team;
                    }
                }

                return null;
            },
        );

        $snapshotRepository = $this->createMock(TeamRankingSnapshotRepository::class);
        $snapshotRepository->method('findRankingForMatch')->willReturn($ranking);

        $gameMatchRepository = $this->createMock(GameMatchRepository::class);
        $gameMatchRepository->method('findLastScoredMatchBefore')->willReturn($previousMatch);

        return new KdoMatchWinnerService(
            $pronosticRepository,
            $teamMemberRepository,
            $teamRepository,
            $snapshotRepository,
            $gameMatchRepository,
        );
    }

    private function finishedKdoMatch(bool $isKdo = true): GameMatch
    {
        $home = (new Country())->setNom('France');
        $away = (new Country())->setNom('Allemagne');

        $match = (new GameMatch())
            ->setPaysDomicile($home)
            ->setPaysExterieur($away)
            ->setDateHeure(new \DateTimeImmutable())
            ->setScoreDomicile(1)
            ->setScoreExterieur(1)
            ->setIsKdoMatch($isKdo);

        $this->setId($match, 1);

        return $match;
    }

    private function team(int $id, string $name): Team
    {
        $team = (new Team())->setName($name);
        $this->setId($team, $id);

        return $team;
    }

    private function exactPronostic(GameMatch $match, int $playerId, int $home, int $away): Pronostic
    {
        return $this->pronostic($match, $playerId, $home, $away);
    }

    private function pronostic(GameMatch $match, int $playerId, int $home, int $away): Pronostic
    {
        $user = new User();
        $this->setId($user, $playerId);
        $user->setEmail('player'.$playerId.'@test.local');

        return (new Pronostic())
            ->setMatch($match)
            ->setJoueur($user)
            ->setScoreDomicile($home)
            ->setScoreExterieur($away);
    }

    private function setId(object $entity, int $id): void
    {
        (new \ReflectionProperty($entity, 'id'))->setValue($entity, $id);
    }
}
