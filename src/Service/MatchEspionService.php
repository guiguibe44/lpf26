<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Repository\PronosticRepository;
use App\Repository\TeamJokerUsageRepository;

/**
 * Joker « espion » : renseignements sur un match avant le coup d'envoi.
 */
final class MatchEspionService
{
    public function __construct(
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly PronosticRepository $pronosticRepository,
        private readonly MatchCotePreviewService $matchCotePreviewService,
        private readonly MatchStatusResolver $matchStatusResolver,
    ) {
    }

    public function teamHasEspionOnMatchBeforeKickoff(Team $team, GameMatch $match, ?\DateTimeImmutable $now = null): bool
    {
        if (!$this->matchStatusResolver->canEditBeforeKickoff($match, $now)) {
            return false;
        }

        $usage = $this->teamJokerUsageRepository->findOneByTeamAndMatch($team, $match);
        if (!$usage instanceof TeamJokerUsage) {
            return false;
        }

        return Joker::CODE_ESPION === $usage->getJoker()?->getCode();
    }

    /**
     * @param list<GameMatch> $matches
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildIntelByMatchIdForTeam(Team $team, array $matches, ?\DateTimeImmutable $now = null): array
    {
        $map = [];
        foreach ($matches as $match) {
            $matchId = $match->getId();
            if (null === $matchId) {
                continue;
            }

            if (!$this->teamHasEspionOnMatchBeforeKickoff($team, $match, $now)) {
                continue;
            }

            $intel = $this->buildIntelForMatch($match);
            if (null !== $intel) {
                $map[(int) $matchId] = $intel;
            }
        }

        return $map;
    }

    /**
     * @return array{
     *     cotes: array{min: ?float, moyenne: ?float, max: ?float, pronostics_count: int},
     *     jokers: list<array{
     *         team_id: int,
     *         team_name: string,
     *         team_logo: ?string,
     *         joker_name: string,
     *         joker_code: string,
     *         joker_image: ?string,
     *         target_team_name: ?string
     *     }>
     * }|null
     */
    public function buildIntelForMatch(GameMatch $match): ?array
    {
        $pronostics = $this->pronosticRepository->findByMatchWithTeamMembers($match);
        $cotes = $this->matchCotePreviewService->computeForMatch($match, $pronostics);

        $jokers = [];
        foreach ($this->teamJokerUsageRepository->findByMatch($match) as $usage) {
            $usageTeam = $usage->getTeam();
            $joker = $usage->getJoker();
            $teamId = $usageTeam?->getId();
            if (null === $teamId || !$joker instanceof Joker) {
                continue;
            }

            $target = $usage->getTargetTeam();
            $jokers[] = [
                'team_id' => (int) $teamId,
                'team_name' => (string) $usageTeam->getName(),
                'team_logo' => $usageTeam->getLogo(),
                'joker_name' => (string) $joker->getName(),
                'joker_code' => (string) $joker->getCode(),
                'joker_image' => $joker->getImage(),
                'target_team_name' => $target instanceof Team ? (string) $target->getName() : null,
            ];
        }

        usort(
            $jokers,
            static fn (array $a, array $b): int => strcmp($a['team_name'], $b['team_name']),
        );

        return [
            'cotes' => $cotes,
            'jokers' => $jokers,
        ];
    }
}
