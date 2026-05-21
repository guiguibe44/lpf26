<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SimulatedPronosticLine;
use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Enum\JokerLiveStoryCase;
use App\Repository\ButRepository;
use App\Repository\JokerRepository;
use App\Repository\TeamJokerUsageRepository;

/**
 * Explications des jokers actifs sur un match : équipe bénéficiaire et impact points
 * selon le score affiché (réel ou simulé) et les pronostics.
 */
final class MatchJokerImpactExplanationBuilder
{
    public function __construct(
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly JokerRepository $jokerRepository,
        private readonly JokerDefenseService $jokerDefenseService,
        private readonly PronosticSimulationService $pronosticSimulationService,
        private readonly PronosticScoreInversionService $pronosticScoreInversionService,
        private readonly ButRepository $butRepository,
        private readonly ButeurJokerPointsService $buteurJokerPointsService,
        private readonly JokerLiveStoryTemplateRenderer $liveStoryRenderer,
    ) {
    }

    /**
     * @param list<SimulatedPronosticLine> $linesAfterSimulate
     * @param list<SimulatedPronosticLine> $linesAfterSteal
     * @param list<SimulatedPronosticLine> $finalLines
     * @param list<Team>                   $teams
     *
     * @return list<array{
     *     code: string,
     *     name: string,
     *     icon: string,
     *     image: ?string,
     *     neutralized: bool,
     *     stories: list<string>
     * }>
     */
    public function buildForMatch(
        GameMatch $match,
        int $scoreHome,
        int $scoreAway,
        array $linesAfterSimulate,
        array $linesAfterSteal,
        array $finalLines,
        array $teams,
    ): array {
        $scoreLabel = sprintf('%d-%d', $scoreHome, $scoreAway);
        $rawTotals = $this->sumTeamPointsByTeamId($linesAfterSimulate);
        $stealTotals = $this->sumTeamPointsByTeamId($linesAfterSteal);
        $finalTotals = $this->sumTeamPointsByTeamId($finalLines);
        $buteurTotals = $this->buildButeurPointsByTeamId($match, $teams);
        $items = [];

        foreach ($this->teamJokerUsageRepository->findByMatch($match) as $usage) {
            $joker = $usage->getJoker();
            $code = $joker?->getCode();
            if (null === $code || '' === $code || Joker::CODE_COLLECTE_POINTS === $code) {
                continue;
            }

            if (Joker::CODE_ESPION === $code) {
                $items[] = $this->buildEspionItem($usage);

                continue;
            }

            $items[] = $this->buildUsageItem(
                $usage,
                $match,
                $scoreHome,
                $scoreAway,
                $scoreLabel,
                $linesAfterSimulate,
                $rawTotals,
                $stealTotals,
                $finalTotals,
                $buteurTotals,
            );
        }

        foreach ($this->teamJokerUsageRepository->findCollecteTeamIdsForMatch($match) as $collectorId) {
            $items[] = $this->buildCollecteItem(
                $match,
                (int) $collectorId,
                $stealTotals,
                $finalTotals,
                $teams,
            );
        }

        return $items;
    }

    /**
     * @param array<int, float> $rawTotals
     * @param array<int, float> $stealTotals
     * @param array<int, float> $finalTotals
     * @param array<int, array{base: int, with_joker: int}> $buteurTotals
     *
     * @return array<string, mixed>
     */
    private function buildUsageItem(
        TeamJokerUsage $usage,
        GameMatch $match,
        int $scoreHome,
        int $scoreAway,
        string $scoreLabel,
        array $linesAfterSimulate,
        array $rawTotals,
        array $stealTotals,
        array $finalTotals,
        array $buteurTotals,
    ): array {
        $joker = $usage->getJoker();
        $code = (string) $joker?->getCode();
        $placerName = $this->teamName($usage->getTeam());
        $targetName = $this->teamName($usage->getTargetTeam());
        $placerId = (int) ($usage->getTeam()?->getId() ?? 0);
        $targetId = (int) ($usage->getTargetTeam()?->getId() ?? 0);
        $neutralized = $this->jokerDefenseService->isUsageNeutralized($usage);
        $jokerName = $joker instanceof Joker ? $joker->getDisplayTitle() : $code;

        if (Joker::CODE_BOUCLIER === $code) {
            $stories = $this->liveStoryRenderer->render($joker, JokerLiveStoryCase::ShieldActive, [
                'equipe_poseuse' => $placerName,
            ]);
        } elseif ($neutralized && JokerDefenseService::isOffensiveAgainstTeam($code)) {
            $stories = array_merge(
                $this->renderJokerPlaced($joker, $placerName, $targetName),
                $this->liveStoryRenderer->render($joker, JokerLiveStoryCase::Neutralized, [
                    'equipe_cible' => $targetName,
                ]),
            );
        } else {
            $targetForIntro = $this->jokerTargetsOpponent($code) && $targetId > 0 ? $targetName : null;
            $stories = array_merge(
                $this->renderJokerPlaced($joker, $placerName, $targetForIntro),
                $this->buildOutcomeStories(
                    $joker,
                    $code,
                    $placerId,
                    $placerName,
                    $targetId,
                    $targetName,
                    $match,
                    $scoreHome,
                    $scoreAway,
                    $linesAfterSimulate,
                    $rawTotals,
                    $stealTotals,
                    $buteurTotals,
                ),
            );
        }

        return [
            'code' => $code,
            'name' => $jokerName,
            'icon' => Joker::tablerIconClassForCode($code),
            'image' => $joker?->getImage(),
            'neutralized' => $neutralized,
            'stories' => $stories,
        ];
    }

    private function jokerTargetsOpponent(string $code): bool
    {
        return JokerDefenseService::isOffensiveAgainstTeam($code)
            || Joker::CODE_INVERSE_SCORE === $code
            || Joker::CODE_INVERSE_BUTEUR === $code;
    }

    /**
     * @return list<string>
     */
    private function renderJokerPlaced(?Joker $joker, string $placerName, ?string $targetName = null): array
    {
        if (null !== $targetName && 'Équipe inconnue' !== $targetName) {
            return $this->liveStoryRenderer->render($joker, JokerLiveStoryCase::PlacedOnTarget, [
                'equipe_poseuse' => $placerName,
                'equipe_cible' => $targetName,
            ]);
        }

        return $this->liveStoryRenderer->render($joker, JokerLiveStoryCase::Placed, [
            'equipe_poseuse' => $placerName,
        ]);
    }

    /**
     * @return list<string>
     */
    private function renderTeamPointsOutcome(?Joker $joker, string $teamName, int $delta, bool $buteur = false): array
    {
        $abs = abs($delta);
        $variables = [
            'equipe' => $teamName,
            'points' => $abs,
            'points_label' => JokerLiveStoryTemplateRenderer::pointsLabel($abs),
            'suffixe_buteurs' => $buteur ? ' sur les buteurs' : '',
        ];

        if ($delta > 0) {
            $case = $buteur ? JokerLiveStoryCase::PointsGainButeur : JokerLiveStoryCase::PointsGain;
        } elseif ($delta < 0) {
            $case = $buteur ? JokerLiveStoryCase::PointsLossButeur : JokerLiveStoryCase::PointsLoss;
        } else {
            $case = JokerLiveStoryCase::PointsNeutral;
        }

        return $this->liveStoryRenderer->render($joker, $case, $variables);
    }

    /**
     * @param list<SimulatedPronosticLine> $linesAfterSimulate
     * @param array<int, float>          $rawTotals
     * @param array<int, float>          $stealTotals
     * @param array<int, array{base: int, with_joker: int}> $buteurTotals
     *
     * @return list<string>
     */
    private function buildOutcomeStories(
        ?Joker $joker,
        string $code,
        int $placerId,
        string $placerName,
        int $targetId,
        string $targetName,
        GameMatch $match,
        int $scoreHome,
        int $scoreAway,
        array $linesAfterSimulate,
        array $rawTotals,
        array $stealTotals,
        array $buteurTotals,
    ): array {
        return match ($code) {
            Joker::CODE_DOUBLE_EQUIPE => $this->outcomeStoriesDoubleEquipe(
                $joker,
                $placerId,
                $placerName,
                $match,
                $scoreHome,
                $scoreAway,
                $linesAfterSimulate,
                $rawTotals,
            ),
            Joker::CODE_INVERSE_SCORE => $this->outcomeStoriesInverseScore(
                $joker,
                $targetId,
                $targetName,
                $match,
                $scoreHome,
                $scoreAway,
                $linesAfterSimulate,
                $rawTotals,
            ),
            Joker::CODE_PIQUE_POINTS => $this->outcomeStoriesPiquePoints(
                $joker,
                $placerId,
                $placerName,
                $targetId,
                $targetName,
                $rawTotals,
                $stealTotals,
            ),
            Joker::CODE_DOUBLE_BUTEUR => $this->outcomeStoriesDoubleButeur($joker, $placerId, $placerName, $buteurTotals),
            Joker::CODE_INVERSE_BUTEUR => $this->outcomeStoriesInverseButeur($joker, $targetId, $targetName, $buteurTotals),
            default => [],
        };
    }

    /**
     * @param list<SimulatedPronosticLine> $linesAfterSimulate
     * @param array<int, float>          $rawTotals
     *
     * @return list<string>
     */
    private function outcomeStoriesDoubleEquipe(
        ?Joker $joker,
        int $placerId,
        string $placerName,
        GameMatch $match,
        int $scoreHome,
        int $scoreAway,
        array $linesAfterSimulate,
        array $rawTotals,
    ): array {
        $with = (int) round((float) ($rawTotals[$placerId] ?? 0.0));
        $without = (int) round($this->sumStandardPronosticForTeam(
            $linesAfterSimulate,
            $placerId,
            $match,
            $scoreHome,
            $scoreAway,
            Joker::CODE_DOUBLE_EQUIPE,
        ));

        return $this->renderTeamPointsOutcome($joker, $placerName, $with - $without);
    }

    /**
     * @param list<SimulatedPronosticLine> $linesAfterSimulate
     * @param array<int, float>          $rawTotals
     *
     * @return list<string>
     */
    private function outcomeStoriesInverseScore(
        ?Joker $joker,
        int $targetId,
        string $targetName,
        GameMatch $match,
        int $scoreHome,
        int $scoreAway,
        array $linesAfterSimulate,
        array $rawTotals,
    ): array {
        $with = (int) round((float) ($rawTotals[$targetId] ?? 0.0));
        $without = (int) round($this->sumStandardPronosticForTeam(
            $linesAfterSimulate,
            $targetId,
            $match,
            $scoreHome,
            $scoreAway,
            null,
            invertScores: false,
        ));

        return $this->renderTeamPointsOutcome($joker, $targetName, $with - $without);
    }

    /**
     * @param array<int, float> $rawTotals
     * @param array<int, float> $stealTotals
     *
     * @return list<string>
     */
    private function outcomeStoriesPiquePoints(
        ?Joker $joker,
        int $placerId,
        string $placerName,
        int $targetId,
        string $targetName,
        array $rawTotals,
        array $stealTotals,
    ): array {
        $deltaThief = (int) round((float) ($stealTotals[$placerId] ?? 0.0))
            - (int) round((float) ($rawTotals[$placerId] ?? 0.0));
        $deltaVictim = (int) round((float) ($stealTotals[$targetId] ?? 0.0))
            - (int) round((float) ($rawTotals[$targetId] ?? 0.0));

        return array_merge(
            $this->renderTeamPointsOutcome($joker, $placerName, $deltaThief),
            $this->renderTeamPointsOutcome($joker, $targetName, $deltaVictim),
        );
    }

    /**
     * @param array<int, array{base: int, with_joker: int}> $buteurTotals
     *
     * @return list<string>
     */
    private function outcomeStoriesDoubleButeur(?Joker $joker, int $placerId, string $placerName, array $buteurTotals): array
    {
        $base = (int) ($buteurTotals[$placerId]['base'] ?? 0);
        $with = (int) ($buteurTotals[$placerId]['with_joker'] ?? 0);

        return $this->renderTeamPointsOutcome($joker, $placerName, $with - $base, true);
    }

    /**
     * @param array<int, array{base: int, with_joker: int}> $buteurTotals
     *
     * @return list<string>
     */
    private function outcomeStoriesInverseButeur(?Joker $joker, int $targetId, string $targetName, array $buteurTotals): array
    {
        $base = (int) ($buteurTotals[$targetId]['base'] ?? 0);
        $with = (int) ($buteurTotals[$targetId]['with_joker'] ?? 0);

        return $this->renderTeamPointsOutcome($joker, $targetName, $with - $base, true);
    }

    /**
     * @param array<int, float> $stealTotals
     * @param array<int, float> $finalTotals
     * @param list<Team>        $teams
     *
     * @return array<string, mixed>
     */
    private function buildCollecteItem(
        GameMatch $match,
        int $collectorId,
        array $stealTotals,
        array $finalTotals,
        array $teams,
    ): array {
        $collectorName = $this->teamNameById($teams, $collectorId);
        $collecteJoker = $this->jokerRepository->findOneBy(['code' => Joker::CODE_COLLECTE_POINTS]);
        $code = Joker::CODE_COLLECTE_POINTS;
        $jokerName = $collecteJoker instanceof Joker ? $collecteJoker->getDisplayTitle() : 'Collecte de points';
        $deltaCollector = (int) round((float) ($finalTotals[$collectorId] ?? 0.0))
            - (int) round((float) ($stealTotals[$collectorId] ?? 0.0));

        $stories = array_merge(
            $this->renderJokerPlaced($collecteJoker, $collectorName),
            $this->renderTeamPointsOutcome($collecteJoker, $collectorName, $deltaCollector),
        );

        foreach ($teams as $team) {
            $teamId = (int) ($team->getId() ?? 0);
            if ($teamId <= 0 || $teamId === $collectorId) {
                continue;
            }

            $delta = (int) round((float) ($finalTotals[$teamId] ?? 0.0))
                - (int) round((float) ($stealTotals[$teamId] ?? 0.0));
            if (0 === $delta) {
                continue;
            }

            $stories = array_merge(
                $stories,
                $this->renderTeamPointsOutcome($collecteJoker, (string) $team->getName(), $delta),
            );
        }

        return [
            'code' => $code,
            'name' => $jokerName,
            'icon' => Joker::tablerIconClassForCode($code),
            'image' => $collecteJoker?->getImage(),
            'neutralized' => false,
            'stories' => $stories,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEspionItem(TeamJokerUsage $usage): array
    {
        $joker = $usage->getJoker();
        $code = Joker::CODE_ESPION;
        $placerName = $this->teamName($usage->getTeam());

        $jokerName = $joker instanceof Joker ? $joker->getDisplayTitle() : 'Espion';

        return [
            'code' => $code,
            'name' => $jokerName,
            'icon' => Joker::tablerIconClassForCode($code),
            'image' => $joker?->getImage(),
            'neutralized' => false,
            'stories' => $this->liveStoryRenderer->render($joker, JokerLiveStoryCase::Espion, [
                'equipe_poseuse' => $placerName,
            ]),
        ];
    }

    /**
     * @param list<SimulatedPronosticLine> $lines
     */
    private function sumStandardPronosticForTeam(
        array $lines,
        int $teamId,
        GameMatch $match,
        int $scoreHome,
        int $scoreAway,
        ?string $skipJokerCode,
        bool $invertScores = true,
    ): float {
        $total = 0.0;
        foreach ($lines as $line) {
            if ($line->teamId !== $teamId) {
                continue;
            }

            $home = $line->predHome;
            $away = $line->predAway;
            if ($invertScores && $line->scoreInverted) {
                $stored = $this->pronosticScoreInversionService->effectiveScores($home, $away, true);
                $home = $stored['home'];
                $away = $stored['away'];
            }

            $base = $this->pronosticSimulationService->computeBasePoints(
                $match,
                $scoreHome,
                $scoreAway,
                $home,
                $away,
            );
            $standard = (float) round($base * $line->coefficient);

            if (Joker::CODE_DOUBLE_EQUIPE === $skipJokerCode) {
                $total += $standard;

                continue;
            }

            $total += $standard;
        }

        return $total;
    }

    /**
     * @param list<Team> $teams
     *
     * @return array<int, array{base: int, with_joker: int}>
     */
    private function buildButeurPointsByTeamId(GameMatch $match, array $teams): array
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
                $tid = (int) $teamId;
                $teamIdsByButeurId[$buteurId] ??= [];
                if (!\in_array($tid, $teamIdsByButeurId[$buteurId], true)) {
                    $teamIdsByButeurId[$buteurId][] = $tid;
                }
            }
        }

        $totals = [];
        foreach ($teams as $team) {
            $teamId = $team->getId();
            if (null !== $teamId) {
                $totals[(int) $teamId] = ['base' => 0, 'with_joker' => 0];
            }
        }

        foreach ($goals as $goal) {
            foreach ($teamIdsByButeurId[$goal['buteur_id']] ?? [] as $teamId) {
                $team = $this->findTeamById($teams, $teamId);
                if (!$team instanceof Team) {
                    continue;
                }

                $points = (int) $goal['points'];
                $totals[$teamId]['base'] = ($totals[$teamId]['base'] ?? 0) + $points;
                $with = $points;
                if ($this->buteurJokerPointsService->teamHasDoubleButeurJokerOnMatch($team, $match)) {
                    $with *= 2;
                }

                if ($this->buteurJokerPointsService->teamIsTargetOfInvertButeurJokerOnMatch($team, $match)) {
                    $with = -abs($with);
                }

                $totals[$teamId]['with_joker'] = ($totals[$teamId]['with_joker'] ?? 0) + $with;
            }
        }

        return $totals;
    }

    /**
     * @param list<SimulatedPronosticLine> $lines
     *
     * @return array<int, float>
     */
    private function sumTeamPointsByTeamId(array $lines): array
    {
        $totals = [];
        foreach ($lines as $line) {
            if ($line->teamId <= 0) {
                continue;
            }

            $totals[$line->teamId] = ($totals[$line->teamId] ?? 0.0) + $line->teamPoints;
        }

        return $totals;
    }

    private function teamName(?Team $team): string
    {
        if (!$team instanceof Team) {
            return 'Équipe inconnue';
        }

        $name = trim((string) $team->getName());

        return '' !== $name ? $name : 'Équipe inconnue';
    }

    /**
     * @param list<Team> $teams
     */
    private function teamNameById(array $teams, int $teamId): string
    {
        return $this->teamName($this->findTeamById($teams, $teamId));
    }

    /**
     * @param list<Team> $teams
     */
    private function findTeamById(array $teams, int $teamId): ?Team
    {
        foreach ($teams as $team) {
            if ((int) ($team->getId() ?? 0) === $teamId) {
                return $team;
            }
        }

        return null;
    }
}
