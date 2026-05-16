<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\KdoMatchOutlook;
use App\Dto\MatchLiveTeamRow;
use App\Dto\SimulatedPronosticLine;
use App\Entity\Buteur;
use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Repository\PronosticRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRankingSnapshotRepository;
use App\Repository\TeamRepository;

final class MatchLiveViewBuilder
{
    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly PronosticRepository $pronosticRepository,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly TeamRankingSnapshotRepository $teamRankingSnapshotRepository,
        private readonly PronosticSimulationService $pronosticSimulationService,
        private readonly ButeurGoalScoringService $buteurGoalScoringService,
        private readonly DefaultPronosticService $defaultPronosticService,
        private readonly KdoMatchWinnerService $kdoMatchWinnerService,
    ) {
    }

    /**
     * @return array{
     *     scoreDomicile: int,
     *     scoreExterieur: int,
     *     teams: list<MatchLiveTeamRow>,
     *     kdoOutlook: ?KdoMatchOutlook
     * }
     */
    public function build(GameMatch $match, int $scoreDomicile, int $scoreExterieur): array
    {
        $this->defaultPronosticService->ensureDefaultsForMatch($match);

        $pronostics = $this->pronosticRepository->findByMatchWithTeamMembers($match);
        $playerTeamMap = $this->teamMemberRepository->findPlayerTeamMap();
        $playerLabels = $this->buildPlayerLabels();
        $simulatedLines = $this->pronosticSimulationService->simulate(
            $match,
            $scoreDomicile,
            $scoreExterieur,
            $pronostics,
            $playerTeamMap,
            $playerLabels,
        );

        $linesByTeamId = [];
        foreach ($simulatedLines as $line) {
            $linesByTeamId[$line->teamId][] = $line;
        }

        $rankingByTeamId = $this->buildCurrentRankingByTeamId();
        $matchCountryIds = $this->resolveMatchCountryIds($match);
        $teams = $this->teamRepository->findAllWithMembersAndPlayers();

        $teamRows = [];
        foreach ($teams as $team) {
            $teamId = (int) $team->getId();
            $teamPronostics = $this->sortPronosticsForTeam($linesByTeamId[$teamId] ?? []);
            $matchPoints = (int) round(array_sum(array_map(
                static fn (SimulatedPronosticLine $line): float => $line->points,
                $teamPronostics,
            )));
            $previousTotal = (float) ($rankingByTeamId[$teamId]['totalPoints'] ?? 0.0);

            $teamRows[] = new MatchLiveTeamRow(
                $teamId,
                (string) $team->getName(),
                $team->getLogo(),
                (int) ($rankingByTeamId[$teamId]['position'] ?? 9999),
                $matchPoints,
                $previousTotal + $matchPoints,
                0,
                $teamPronostics,
                $this->buildButeursForTeam($team, $matchCountryIds),
            );
        }

        $simulatedPositionByTeamId = $this->computeSimulatedRankingPositions($teamRows);
        $teamRows = array_map(
            static function (MatchLiveTeamRow $row) use ($simulatedPositionByTeamId): MatchLiveTeamRow {
                return new MatchLiveTeamRow(
                    $row->teamId,
                    $row->teamName,
                    $row->teamLogo,
                    $row->rankingPosition,
                    $row->matchPoints,
                    $row->simulatedTotalPoints,
                    $simulatedPositionByTeamId[$row->teamId] ?? 9999,
                    $row->pronostics,
                    $row->buteurs,
                );
            },
            $teamRows,
        );

        usort(
            $teamRows,
            static function (MatchLiveTeamRow $a, MatchLiveTeamRow $b): int {
                return $b->matchPoints <=> $a->matchPoints
                    ?: $b->simulatedTotalPoints <=> $a->simulatedTotalPoints
                    ?: $a->simulatedRankingPosition <=> $b->simulatedRankingPosition
                    ?: strcmp($a->teamName, $b->teamName);
            },
        );

        return [
            'scoreDomicile' => $scoreDomicile,
            'scoreExterieur' => $scoreExterieur,
            'teams' => $teamRows,
            'kdoOutlook' => $this->kdoMatchWinnerService->buildOutlook($match, $scoreDomicile, $scoreExterieur),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildForJson(GameMatch $match, int $scoreDomicile, int $scoreExterieur): array
    {
        $data = $this->build($match, $scoreDomicile, $scoreExterieur);

        return [
            'scoreDomicile' => $data['scoreDomicile'],
            'scoreExterieur' => $data['scoreExterieur'],
            'teams' => array_map(static fn (MatchLiveTeamRow $row): array => $row->toArray(), $data['teams']),
            'kdoOutlook' => $data['kdoOutlook'] instanceof KdoMatchOutlook ? $data['kdoOutlook']->toArray() : null,
        ];
    }

    /**
     * @return array<int, array{position: int, totalPoints: float}>
     */
    private function buildCurrentRankingByTeamId(): array
    {
        $map = [];
        foreach ($this->teamRankingSnapshotRepository->findLatestRanking() as $snapshot) {
            $teamId = $snapshot->getTeam()?->getId();
            if (null !== $teamId) {
                $map[(int) $teamId] = [
                    'position' => $snapshot->getPosition(),
                    'totalPoints' => $snapshot->getTotalPoints(),
                ];
            }
        }

        return $map;
    }

    /**
     * @param list<MatchLiveTeamRow> $teamRows
     *
     * @return array<int, int>
     */
    private function computeSimulatedRankingPositions(array $teamRows): array
    {
        $candidates = $teamRows;
        usort(
            $candidates,
            static function (MatchLiveTeamRow $a, MatchLiveTeamRow $b): int {
                return $b->simulatedTotalPoints <=> $a->simulatedTotalPoints
                    ?: $a->rankingPosition <=> $b->rankingPosition
                    ?: strcmp($a->teamName, $b->teamName);
            },
        );

        $positions = [];
        foreach ($candidates as $index => $row) {
            $positions[$row->teamId] = $index + 1;
        }

        return $positions;
    }

    /**
     * @return array<int, string> playerId => label
     */
    private function buildPlayerLabels(): array
    {
        $labels = [];
        $teams = $this->teamRepository->findAllWithMembersAndPlayers();
        foreach ($teams as $team) {
            $members = $team->getMembers()->toArray();
            usort(
                $members,
                static function (TeamMember $a, TeamMember $b): int {
                    $joinedA = $a->getJoinedAt()->getTimestamp();
                    $joinedB = $b->getJoinedAt()->getTimestamp();

                    return $joinedA <=> $joinedB ?: strcmp((string) $a->getNickname(), (string) $b->getNickname());
                },
            );

            foreach ($members as $index => $member) {
                $playerId = $member->getPlayer()?->getId();
                if (null !== $playerId) {
                    $labels[(int) $playerId] = sprintf('Joueur %d', $index + 1);
                }
            }
        }

        return $labels;
    }

    /**
     * @param list<int> $matchCountryIds
     *
     * @return list<array{name: string, country: string|null, pointsPerGoal: float}>
     */
    private function buildButeursForTeam(Team $team, array $matchCountryIds): array
    {
        if ([] === $matchCountryIds) {
            return [];
        }

        $rows = [];
        foreach ($team->getMembers() as $member) {
            $player = $member->getPlayer();
            if (!$player instanceof User) {
                continue;
            }

            $buteur = $player->getButeurChoisi();
            if (!$buteur instanceof Buteur) {
                continue;
            }

            $countryId = $buteur->getPays()?->getId();
            if (null === $countryId || !\in_array((int) $countryId, $matchCountryIds, true)) {
                continue;
            }

            $coefficient = $this->buteurGoalScoringService->getCurrentCoefficientForButeur($buteur);
            $pointsPerGoal = (float) round(ButeurGoalScoringService::DEFAULT_POINTS_BASE * $coefficient);

            $rows[] = [
                'name' => (string) $buteur,
                'country' => $buteur->getPays()?->getNom(),
                'pointsPerGoal' => $pointsPerGoal,
                'coefficient' => $coefficient,
            ];
        }

        return $rows;
    }

    /**
     * @return list<int>
     */
    private function resolveMatchCountryIds(GameMatch $match): array
    {
        $ids = [];
        foreach ([$match->getPaysDomicile(), $match->getPaysExterieur()] as $country) {
            if ($country instanceof Country && null !== $country->getId()) {
                $ids[] = (int) $country->getId();
            }
        }

        return $ids;
    }

    /**
     * @param list<SimulatedPronosticLine> $lines
     *
     * @return list<SimulatedPronosticLine>
     */
    private function sortPronosticsForTeam(array $lines): array
    {
        usort(
            $lines,
            static fn (SimulatedPronosticLine $a, SimulatedPronosticLine $b): int => strcmp($a->playerLabel, $b->playerLabel),
        );

        return $lines;
    }
}
