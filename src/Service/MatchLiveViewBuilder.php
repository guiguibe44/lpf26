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
use App\Repository\ButRepository;
use App\Repository\PronosticRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamJokerUsageRepository;
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
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly JokerScoringApplicator $jokerScoringApplicator,
        private readonly TeamJokerService $teamJokerService,
        private readonly JokerStealPointsService $jokerStealPointsService,
        private readonly ButeurJokerPointsService $buteurJokerPointsService,
        private readonly PronosticScoreInversionService $pronosticScoreInversionService,
        private readonly JokerCollectePointsService $jokerCollectePointsService,
        private readonly ButRepository $butRepository,
        private readonly MatchCotePreviewService $matchCotePreviewService,
    ) {
    }

    /**
     * @return array{
     *     scoreDomicile: int,
     *     scoreExterieur: int,
     *     teams: list<MatchLiveTeamRow>,
     *     kdoOutlook: ?KdoMatchOutlook,
     *     cotes: array{
     *         score_label: string,
     *         for_score: ?float,
     *         min: ?float,
     *         moyenne: ?float,
     *         max: ?float,
     *         pronostics_count: int
     *     },
     *     matchButeurs: list<array{
     *         id: int,
     *         name: string,
     *         photo: ?string,
     *         country: ?string,
     *         coefficient: float,
     *         selections_count: int,
     *         teams: list<string>
     *     }>
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
            $this->teamJokerUsageRepository->findJokerCodesByTeamForMatch($match),
            $this->jokerScoringApplicator,
            $this->pronosticScoreInversionService->getTargetTeamIdsForMatch($match),
        );
        $simulatedLines = $this->jokerStealPointsService->adjustSimulatedLines($match, $simulatedLines);
        $simulatedLines = $this->jokerCollectePointsService->adjustSimulatedLines($match, $simulatedLines);

        $linesByTeamId = [];
        foreach ($simulatedLines as $line) {
            $linesByTeamId[$line->teamId][] = $line;
        }

        $rankingByTeamId = $this->buildCurrentRankingByTeamId();
        $matchCountryIds = $this->resolveMatchCountryIds($match);
        $jokersByTeamId = $this->teamJokerService->buildActiveJokersByTeamIdForMatch($match);
        $teams = $this->teamRepository->findAllWithMembersAndPlayers();
        $buteurMatchPointsByTeamId = $this->buildButeurMatchPointsByTeamId($match, $teams);

        $teamRows = [];
        foreach ($teams as $team) {
            $teamId = (int) $team->getId();
            $teamPronostics = $this->sortPronosticsForTeam($linesByTeamId[$teamId] ?? []);
            $pronosticMatchPoints = (int) round(array_sum(array_map(
                static fn (SimulatedPronosticLine $line): float => $line->teamPoints,
                $teamPronostics,
            )));
            $buteurMatchPoints = (int) ($buteurMatchPointsByTeamId[$teamId] ?? 0);
            $previousTotal = (float) ($rankingByTeamId[$teamId]['totalPoints'] ?? 0.0);

            $teamRows[] = new MatchLiveTeamRow(
                $teamId,
                (string) $team->getName(),
                $team->getLogo(),
                (int) ($rankingByTeamId[$teamId]['position'] ?? 9999),
                $pronosticMatchPoints,
                $buteurMatchPoints,
                $previousTotal + $pronosticMatchPoints + $buteurMatchPoints,
                0,
                $teamPronostics,
                $this->buildButeursForTeam($team, $match, $matchCountryIds),
                $jokersByTeamId[$teamId] ?? null,
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
                    $row->pronosticMatchPoints,
                    $row->buteurMatchPoints,
                    $row->simulatedTotalPoints,
                    $simulatedPositionByTeamId[$row->teamId] ?? 9999,
                    $row->pronostics,
                    $row->buteurs,
                    $row->activeJoker,
                );
            },
            $teamRows,
        );

        usort(
            $teamRows,
            static function (MatchLiveTeamRow $a, MatchLiveTeamRow $b): int {
                return $b->matchPointsTotal() <=> $a->matchPointsTotal()
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
            'cotes' => $this->matchCotePreviewService->buildDisplayContext($scoreDomicile, $scoreExterieur, $pronostics),
            'matchButeurs' => $this->buildMatchButeurSelections($teams, $matchCountryIds),
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
            'cotes' => $data['cotes'],
            'matchButeurs' => $data['matchButeurs'],
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
     * @param list<Team> $teams
     *
     * @return array<int, int>
     */
    private function buildButeurMatchPointsByTeamId(GameMatch $match, array $teams): array
    {
        $matchId = $match->getId();
        if (null === $matchId) {
            return [];
        }

        $goals = $this->butRepository->findGoalRowsIndexedByMatchIds([$matchId])[$matchId] ?? [];
        if ([] === $goals) {
            return [];
        }

        $buteurToTeamId = [];
        foreach ($teams as $team) {
            $teamId = $team->getId();
            if (null === $teamId) {
                continue;
            }

            foreach ($team->getMembers() as $member) {
                $buteur = $member->getPlayer()?->getButeurChoisi();
                if (null !== $buteur?->getId()) {
                    $buteurToTeamId[(int) $buteur->getId()] = (int) $teamId;
                }
            }
        }

        $pointsByTeamId = [];
        foreach ($goals as $goal) {
            $teamId = $buteurToTeamId[$goal['buteur_id']] ?? null;
            if (null === $teamId) {
                continue;
            }

            $pointsByTeamId[$teamId] = ($pointsByTeamId[$teamId] ?? 0) + (int) $goal['points'];
        }

        return $pointsByTeamId;
    }

    /**
     * @param list<int> $matchCountryIds
     *
     * @return list<array{name: string, photo: ?string, country: string|null, pointsPerGoal: float, coefficient: float, double_joker: bool, invert_joker: bool}>
     */
    private function buildButeursForTeam(Team $team, GameMatch $match, array $matchCountryIds): array
    {
        if ([] === $matchCountryIds) {
            return [];
        }

        $doubleButeurJoker = $this->buteurJokerPointsService->teamHasDoubleButeurJokerOnMatch($team, $match);
        $invertButeurJoker = $this->buteurJokerPointsService->teamIsTargetOfInvertButeurJokerOnMatch($team, $match);

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
            if ($doubleButeurJoker) {
                $pointsPerGoal *= 2.0;
            }

            if ($invertButeurJoker) {
                $pointsPerGoal = -abs($pointsPerGoal);
            }

            $rows[] = [
                'name' => trim((string) $buteur->getNom()),
                'photo' => $buteur->getPhotoPublicPath(),
                'country' => $buteur->getPays()?->getNom(),
                'pointsPerGoal' => $pointsPerGoal,
                'coefficient' => $coefficient,
                'double_joker' => $doubleButeurJoker,
                'invert_joker' => $invertButeurJoker,
            ];
        }

        return $rows;
    }

    /**
     * Buteurs choisis par au moins un joueur, dont le pays est en lice sur ce match (dédoublonnés).
     *
     * @param list<Team> $teams
     * @param list<int> $matchCountryIds
     *
     * @return list<array{
     *     id: int,
     *     name: string,
     *     photo: ?string,
     *     country: ?string,
     *     coefficient: float,
     *     selections_count: int,
     *     teams: list<string>
     * }>
     */
    private function buildMatchButeurSelections(array $teams, array $matchCountryIds): array
    {
        if ([] === $matchCountryIds) {
            return [];
        }

        $byButeurId = [];
        foreach ($teams as $team) {
            $teamName = trim((string) $team->getName());
            foreach ($team->getMembers() as $member) {
                $player = $member->getPlayer();
                if (!$player instanceof User) {
                    continue;
                }

                $buteur = $player->getButeurChoisi();
                if (!$buteur instanceof Buteur) {
                    continue;
                }

                $buteurId = $buteur->getId();
                $countryId = $buteur->getPays()?->getId();
                if (null === $buteurId || null === $countryId || !\in_array((int) $countryId, $matchCountryIds, true)) {
                    continue;
                }

                $key = (int) $buteurId;
                if (!isset($byButeurId[$key])) {
                    $byButeurId[$key] = [
                        'id' => $key,
                        'name' => trim((string) $buteur->getNom()),
                        'photo' => $buteur->getPhotoPublicPath(),
                        'country' => $buteur->getPays()?->getNom(),
                        'coefficient' => $this->buteurGoalScoringService->getCurrentCoefficientForButeur($buteur),
                        'selections_count' => 0,
                        'teams' => [],
                    ];
                }

                ++$byButeurId[$key]['selections_count'];
                if ('' !== $teamName && !\in_array($teamName, $byButeurId[$key]['teams'], true)) {
                    $byButeurId[$key]['teams'][] = $teamName;
                }
            }
        }

        $rows = array_values($byButeurId);
        usort(
            $rows,
            static function (array $a, array $b): int {
                return $b['coefficient'] <=> $a['coefficient']
                    ?: strcmp($a['name'], $b['name']);
            },
        );

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
