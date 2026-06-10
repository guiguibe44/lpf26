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
use App\Entity\Pronostic;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Repository\ButRepository;
use App\Repository\GameMatchRepository;
use App\Repository\PronosticRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamJokerUsageRepository;
use App\Repository\TeamRankingSnapshotRepository;
use App\Repository\TeamRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class MatchLiveViewBuilder
{
    public function __construct(
        private readonly Security $security,
        private readonly TeamRepository $teamRepository,
        private readonly PronosticRepository $pronosticRepository,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly TeamRankingSnapshotRepository $teamRankingSnapshotRepository,
        private readonly GameMatchRepository $gameMatchRepository,
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
        private readonly MatchTeamJokerDisplayBuilder $matchTeamJokerDisplayBuilder,
        private readonly MatchJokerImpactExplanationBuilder $matchJokerImpactExplanationBuilder,
        private readonly PronosticCalcDisplayService $pronosticCalcDisplayService,
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
     *     }>,
     *     viewerPronostic: ?array{
     *         pronostic_id: int,
     *         team_id: int,
     *         pred_home: int,
     *         pred_away: int,
     *         points: int,
     *         coefficient: float,
     *         base_points: int,
     *         score_inverted: bool,
     *         prise_risque: bool
     *     },
     *     matchJokers: list<array{
     *         code: string,
     *         name: string,
     *         icon: string,
     *         image: ?string,
     *         neutralized: bool,
     *         stories: list<string>
     *     }>,
     *     incomingJokerAlerts: list<array{
     *         id: int,
     *         code: string,
     *         name: string,
     *         image: ?string,
     *         icon: string,
     *         placer_team_name: string,
     *         label: string,
     *         description: string
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
        $linesAfterSimulate = $simulatedLines;
        $simulatedLines = $this->jokerStealPointsService->adjustSimulatedLines($match, $simulatedLines);
        $linesAfterSteal = $simulatedLines;
        $simulatedLines = $this->jokerCollectePointsService->adjustSimulatedLines($match, $simulatedLines);
        $simulatedLines = $this->pronosticCalcDisplayService->enrich(
            $match,
            $scoreDomicile,
            $scoreExterieur,
            $linesAfterSimulate,
            $linesAfterSteal,
            $simulatedLines,
        );

        $linesByTeamId = [];
        foreach ($simulatedLines as $line) {
            $linesByTeamId[$line->teamId][] = $line;
        }

        $rankingBeforeByTeamId = $this->buildRankingBeforeMatchByTeamId($match);
        $rankingAfterMatchByTeamId = $this->buildRankingForMatchByTeamId($match);
        $realHome = $match->getScoreDomicile();
        $realAway = $match->getScoreExterieur();
        $useOfficialRankingTotals = null !== $realHome
            && null !== $realAway
            && $scoreDomicile === $realHome
            && $scoreExterieur === $realAway
            && [] !== $rankingAfterMatchByTeamId;
        $matchCountryIds = $this->resolveMatchCountryIds($match);
        $jokersByTeamId = $this->teamJokerService->buildActiveJokersByTeamIdForMatch($match);
        $teams = $this->teamRepository->findAllWithMembersAndPlayers();
        $buteurMatchPointsByTeamId = $this->buildButeurMatchPointsByTeamId($match, $teams);
        $teamIds = array_values(array_filter(array_map(
            static fn (Team $team): ?int => $team->getId(),
            $teams,
        )));
        $jokerBadgesByTeamId = $this->matchTeamJokerDisplayBuilder->buildByTeamIdForMatch($match, $teamIds);

        $teamRows = [];
        foreach ($teams as $team) {
            $teamId = (int) $team->getId();
            $teamPronostics = $this->sortPronosticsForTeam($linesByTeamId[$teamId] ?? []);
            $pronosticMatchPoints = (int) round(array_sum(array_map(
                static fn (SimulatedPronosticLine $line): float => $line->teamPoints,
                $teamPronostics,
            )));
            $buteurMatchPoints = (int) ($buteurMatchPointsByTeamId[$teamId] ?? 0);
            $previousTotal = (float) ($rankingBeforeByTeamId[$teamId]['totalPoints'] ?? 0.0);
            $simulatedTotalPoints = $useOfficialRankingTotals && isset($rankingAfterMatchByTeamId[$teamId])
                ? (float) $rankingAfterMatchByTeamId[$teamId]['totalPoints']
                : $previousTotal + $pronosticMatchPoints + $buteurMatchPoints;

            $teamRows[] = new MatchLiveTeamRow(
                $teamId,
                (string) $team->getName(),
                $team->getLogo(),
                (int) ($rankingBeforeByTeamId[$teamId]['position'] ?? 9999),
                $pronosticMatchPoints,
                $buteurMatchPoints,
                $simulatedTotalPoints,
                0,
                $teamPronostics,
                $this->buildButeursForTeam($team, $match, $matchCountryIds),
                $jokersByTeamId[$teamId] ?? null,
                $jokerBadgesByTeamId[$teamId] ?? [],
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
                    $row->jokerBadges,
                );
            },
            $teamRows,
        );

        usort(
            $teamRows,
            static function (MatchLiveTeamRow $a, MatchLiveTeamRow $b): int {
                return $a->rankingPosition <=> $b->rankingPosition
                    ?: $b->matchPointsTotal() <=> $a->matchPointsTotal()
                    ?: strcmp($a->teamName, $b->teamName);
            },
        );

        $viewerPronostic = $this->buildViewerPronostic($pronostics, $simulatedLines);

        return [
            'scoreDomicile' => $scoreDomicile,
            'scoreExterieur' => $scoreExterieur,
            'teams' => $teamRows,
            'kdoOutlook' => $this->kdoMatchWinnerService->buildOutlook($match, $scoreDomicile, $scoreExterieur),
            'cotes' => $this->matchCotePreviewService->buildDisplayContext($scoreDomicile, $scoreExterieur, $pronostics),
            'matchButeurs' => $this->buildMatchButeurSelections($teams, $matchCountryIds),
            'viewerPronostic' => $viewerPronostic,
            'incomingJokerAlerts' => $this->matchTeamJokerDisplayBuilder->buildIncomingAlertsForTeam(
                $match,
                (int) ($viewerPronostic['team_id'] ?? 0),
            ),
            'matchJokers' => $this->matchJokerImpactExplanationBuilder->buildForMatch(
                $match,
                $scoreDomicile,
                $scoreExterieur,
                $linesAfterSimulate,
                $linesAfterSteal,
                $simulatedLines,
                $teams,
            ),
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
            'viewerPronostic' => $data['viewerPronostic'],
            'incomingJokerAlerts' => $data['incomingJokerAlerts'],
            'matchJokers' => $data['matchJokers'],
        ];
    }

    /**
     * Pronostic du joueur connecté pour ce match (scores effectifs + points simulés).
     *
     * @param iterable<Pronostic>           $pronostics
     * @param list<SimulatedPronosticLine> $simulatedLines
     *
     * @return array{
     *     pronostic_id: int,
     *     team_id: int,
     *     pred_home: int,
     *     pred_away: int,
     *     points: int,
     *     coefficient: float,
     *     base_points: int,
     *     score_inverted: bool,
     *     prise_risque: bool
     * }|null
     */
    private function buildViewerPronostic(iterable $pronostics, array $simulatedLines): ?array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $userId = $user->getId();
        if (null === $userId) {
            return null;
        }

        $viewerPronostic = null;
        foreach ($pronostics as $pronostic) {
            if ($pronostic instanceof Pronostic && $pronostic->getJoueur()?->getId() === $userId) {
                $viewerPronostic = $pronostic;
                break;
            }
        }

        if (!$viewerPronostic instanceof Pronostic) {
            return null;
        }

        $pronosticId = $viewerPronostic->getId();
        if (null === $pronosticId) {
            return null;
        }

        foreach ($simulatedLines as $line) {
            if ($line->pronosticId !== $pronosticId) {
                continue;
            }

            return [
                'pronostic_id' => $pronosticId,
                'team_id' => $line->teamId,
                'pred_home' => $line->predHome,
                'pred_away' => $line->predAway,
                'points' => (int) round($line->teamPoints),
                'coefficient' => $line->coefficient,
                'base_points' => $line->basePoints,
                'score_inverted' => $line->scoreInverted,
                'prise_risque' => $line->priseRisque,
            ];
        }

        $teamId = $this->teamMemberRepository->findOneBy(['player' => $user])?->getTeam()?->getId();
        if (null === $teamId) {
            return null;
        }

        return [
            'pronostic_id' => $pronosticId,
            'team_id' => (int) $teamId,
            'pred_home' => $viewerPronostic->getScoreDomicile() ?? Pronostic::DEFAULT_SCORE_DOMICILE,
            'pred_away' => $viewerPronostic->getScoreExterieur() ?? Pronostic::DEFAULT_SCORE_EXTERIEUR,
            'points' => (int) round((float) ($viewerPronostic->getPointsEquipe() ?? $viewerPronostic->getPoints() ?? 0)),
            'coefficient' => (float) ($viewerPronostic->getCoteCoefficient() ?? 1.0),
            'base_points' => (int) ($viewerPronostic->getPointsBase() ?? 0),
            'score_inverted' => false,
            'prise_risque' => $viewerPronostic->isPriseRisque(),
        ];
    }

    /**
     * Classement cumulé après le dernier match terminé avant celui en cours (évite le double comptage live).
     *
     * @return array<int, array{position: int, totalPoints: float}>
     */
    private function buildRankingBeforeMatchByTeamId(GameMatch $match): array
    {
        $previousMatch = $this->gameMatchRepository->findLastScoredMatchBefore($match);
        if (!$previousMatch instanceof GameMatch) {
            return [];
        }

        $map = [];
        foreach ($this->teamRankingSnapshotRepository->findRankingForMatch($previousMatch) as $snapshot) {
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
     * Snapshot officiel après ce match (recalculé à chaque but en live).
     *
     * @return array<int, array{position: int, totalPoints: float}>
     */
    private function buildRankingForMatchByTeamId(GameMatch $match): array
    {
        $map = [];
        foreach ($this->teamRankingSnapshotRepository->findRankingForMatch($match) as $snapshot) {
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

        /** @var array<int, list<int>> $teamIdsByButeurId */
        $teamIdsByButeurId = [];
        foreach ($teams as $team) {
            $teamId = $team->getId();
            if (null === $teamId) {
                continue;
            }

            foreach ($team->getMembers() as $member) {
                $buteur = $member->getPlayer()?->getButeurChoisi();
                if (null === $buteur?->getId()) {
                    continue;
                }

                $buteurId = (int) $buteur->getId();
                $teamIdsByButeurId[$buteurId] ??= [];
                $tid = (int) $teamId;
                if (!\in_array($tid, $teamIdsByButeurId[$buteurId], true)) {
                    $teamIdsByButeurId[$buteurId][] = $tid;
                }
            }
        }

        $pointsByTeamId = [];
        foreach ($goals as $goal) {
            foreach ($teamIdsByButeurId[$goal['buteur_id']] ?? [] as $teamId) {
                $pointsByTeamId[$teamId] = ($pointsByTeamId[$teamId] ?? 0) + (int) $goal['points'];
            }
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
            $pointsPerGoal = (float) $this->buteurGoalScoringService->getPointsPerGoalForButeur($buteur);
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
