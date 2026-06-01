<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Repository\TeamJokerUsageRepository;
use App\Repository\TeamRecapGifRepository;
use App\Repository\TeamRepository;
use App\Service\JokerDefenseService;
use App\Service\TeamRecapGifPicker;
use App\Service\TeamRecapGifUrlBuilder;
use App\Service\TeamRecapJokerGifResolver;
use App\TeamRecap\TeamRecapGifSlot;
use PHPUnit\Framework\TestCase;

final class TeamRecapJokerGifResolverTest extends TestCase
{
    public function testResolvePrefersPlacedJokerOverSuffered(): void
    {
        $team = $this->createTeam(1);
        $match = $this->createMatch(10, 1_700_000_000);

        $placedUsage = $this->createUsage($team, Joker::CODE_DOUBLE_EQUIPE, $match);
        $sufferedUsage = $this->createUsage(
            $this->createTeam(2),
            Joker::CODE_PIQUE_POINTS,
            $match,
            $team,
        );

        $gifRepo = $this->createMock(TeamRecapGifRepository::class);
        $gifRepo->method('findActivePathsBySlot')->willReturnCallback(
            static function (string $slot): array {
                if (TeamRecapGifSlot::jokerUseful(Joker::CODE_DOUBLE_EQUIPE) === $slot) {
                    return ['/uploads/recap-email/placed.gif'];
                }

                return [];
            },
        );

        $resolver = $this->createResolver(
            usagesByMatch: [$placedUsage, $sufferedUsage],
            protectedTeamIdsOnMatch: [],
            gifRepo: $gifRepo,
        );

        self::assertSame(
            'https://lpf26.test/uploads/recap-email/placed.gif',
            $resolver->resolveAbsoluteUrl($team, [$match], [10 => 12]),
        );
    }

    public function testResolveSufferedUsefulWhenOffensiveJokerNeutralized(): void
    {
        $team = $this->createTeam(1);
        $match = $this->createMatch(11, 1_700_000_100);
        $usage = $this->createUsage(
            $this->createTeam(3),
            Joker::CODE_PIQUE_POINTS,
            $match,
            $team,
        );

        $gifRepo = $this->createMock(TeamRecapGifRepository::class);
        $gifRepo->method('findActivePathsBySlot')
            ->with(TeamRecapGifSlot::jokerUseful(Joker::CODE_PIQUE_POINTS))
            ->willReturn(['/uploads/recap-email/pique-useful.gif']);

        $resolver = $this->createResolver(
            usagesByMatch: [$usage],
            protectedTeamIdsOnMatch: [1 => true],
            gifRepo: $gifRepo,
        );

        self::assertStringContainsString('pique-useful.gif', (string) $resolver->resolveAbsoluteUrl($team, [$match], [11 => 0]));
    }

    public function testResolveReturnsNullWhenNoGifConfigured(): void
    {
        $team = $this->createTeam(1);
        $match = $this->createMatch(12, 1_700_000_200);
        $usage = $this->createUsage($team, Joker::CODE_ESPION, $match);

        $gifRepo = $this->createMock(TeamRecapGifRepository::class);
        $gifRepo->method('findActivePathsBySlot')->willReturn([]);

        $resolver = $this->createResolver(
            usagesByMatch: [$usage],
            protectedTeamIdsOnMatch: [],
            gifRepo: $gifRepo,
        );

        self::assertNull($resolver->resolveAbsoluteUrl($team, [$match], [12 => 5]));
    }

    /**
     * @param list<TeamJokerUsage> $usagesByMatch
     * @param array<int, true>     $protectedTeamIdsOnMatch
     */
    private function createResolver(
        array $usagesByMatch,
        array $protectedTeamIdsOnMatch,
        TeamRecapGifRepository $gifRepo,
    ): TeamRecapJokerGifResolver {
        $usageRepo = $this->createMock(TeamJokerUsageRepository::class);
        $usageRepo->method('findByMatch')->willReturn($usagesByMatch);
        $usageRepo->method('findProtectedTeamIdsForMatchdayOfMatch')->willReturn($protectedTeamIdsOnMatch);

        $teamRepo = $this->createMock(TeamRepository::class);
        $teamRepo->method('findTeamIdsWithFavoriteCountryInGroupMatch')->willReturn([]);

        $picker = new TeamRecapGifPicker($gifRepo, new TeamRecapGifUrlBuilder('https://lpf26.test'));

        return new TeamRecapJokerGifResolver(
            $usageRepo,
            new JokerDefenseService($usageRepo, $teamRepo),
            $picker,
        );
    }

    private function createTeam(int $id): Team
    {
        $team = new Team();
        $reflection = new \ReflectionProperty(Team::class, 'id');
        $reflection->setValue($team, $id);

        return $team;
    }

    private function createMatch(int $id, int $timestamp): GameMatch
    {
        $match = new GameMatch();
        $reflection = new \ReflectionProperty(GameMatch::class, 'id');
        $reflection->setValue($match, $id);
        $match->setDateHeure((new \DateTimeImmutable())->setTimestamp($timestamp));

        return $match;
    }

    private function createUsage(Team $owner, string $code, GameMatch $match, ?Team $target = null): TeamJokerUsage
    {
        $joker = (new Joker())->setCode($code)->setName($code)->setTitle($code);

        $usage = new TeamJokerUsage();
        $usage->setTeam($owner);
        $usage->setJoker($joker);
        $usage->setMatch($match);
        if ($target instanceof Team) {
            $usage->setTargetTeam($target);
        }

        return $usage;
    }
}
