<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SimulatedPronosticLine;
use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
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
     *     placer_team: string,
     *     beneficiary_team: string,
     *     target_team: ?string,
     *     neutralized: bool,
     *     score_label: string,
     *     summary: string,
     *     description: ?string,
     *     technical_lines: list<string>,
     *     impact_rows: list<array{team: string, label: string, points: int, delta: ?int, baseline: ?int}>
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
                $items[] = $this->buildEspionItem($usage, $scoreLabel);

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
                $scoreLabel,
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
        $impactRows = [];

        $beneficiaryName = $placerName;
        $beneficiaryId = $placerId;

        if (Joker::CODE_BOUCLIER === $code) {
            $beneficiaryName = $placerName;
            $summary = sprintf(
                'Équipe bénéficiaire : %s. Score retenu : %s. Protégée pour la journée : les jokers offensifs qui la ciblent sont neutralisés.',
                $beneficiaryName,
                $scoreLabel,
            );
        } elseif ($neutralized && JokerDefenseService::isOffensiveAgainstTeam($code)) {
            $beneficiaryName = $targetName;
            $beneficiaryId = $targetId;
            $summary = sprintf(
                'Équipe bénéficiaire : %s (protection). %s a joué %s sur %s, mais la cible était protégée : joker consommé sans effet. Score retenu : %s.',
                $beneficiaryName,
                $placerName,
                $joker instanceof Joker ? $joker->getDisplayTitle() : $code,
                $targetName,
                $scoreLabel,
            );
        } else {
            [$beneficiaryName, $beneficiaryId, $impactRows, $effectSummary] = $this->buildPointsImpact(
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
                $finalTotals,
                $buteurTotals,
            );

            $summary = sprintf(
                'Équipe bénéficiaire : %s. Score retenu : %s. %s',
                $beneficiaryName,
                $scoreLabel,
                $effectSummary,
            );
        }

        return [
            'code' => $code,
            'name' => $joker instanceof Joker ? $joker->getDisplayTitle() : $code,
            'icon' => Joker::tablerIconClassForCode($code),
            'image' => $joker?->getImage(),
            'placer_team' => $placerName,
            'beneficiary_team' => $beneficiaryName,
            'target_team' => $targetId > 0 ? $targetName : null,
            'neutralized' => $neutralized,
            'score_label' => $scoreLabel,
            'summary' => $summary,
            'description' => $joker?->getDescription(),
            'technical_lines' => $joker instanceof Joker ? $joker->getTechnicalExplanationLines() : [],
            'impact_rows' => $impactRows,
        ];
    }

    /**
     * @param array<int, float> $rawTotals
     * @param array<int, float> $stealTotals
     * @param array<int, float> $finalTotals
     * @param array<int, array{base: int, with_joker: int}> $buteurTotals
     *
     * @return array{0: string, 1: int, 2: list<array{team: string, label: string, points: int, delta: ?int, baseline: ?int}>, 3: string}
     */
    private function buildPointsImpact(
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
        array $finalTotals,
        array $buteurTotals,
    ): array {
        $impactRows = [];

        return match ($code) {
            Joker::CODE_DOUBLE_EQUIPE => $this->impactDoubleEquipe(
                $placerId,
                $placerName,
                $match,
                $scoreHome,
                $scoreAway,
                $linesAfterSimulate,
                $rawTotals,
            ),
            Joker::CODE_INVERSE_SCORE => $this->impactInverseScore(
                $placerId,
                $placerName,
                $targetId,
                $targetName,
                $match,
                $scoreHome,
                $scoreAway,
                $linesAfterSimulate,
                $rawTotals,
            ),
            Joker::CODE_PIQUE_POINTS => $this->impactPiquePoints(
                $match,
                $placerId,
                $placerName,
                $targetId,
                $targetName,
                $rawTotals,
                $stealTotals,
            ),
            Joker::CODE_DOUBLE_BUTEUR => $this->impactDoubleButeur($placerId, $placerName, $buteurTotals),
            Joker::CODE_INVERSE_BUTEUR => $this->impactInverseButeur(
                $placerId,
                $placerName,
                $targetId,
                $targetName,
                $buteurTotals,
            ),
            default => [$placerName, $placerId, $impactRows, 'Effet actif sur ce match (points non détaillés ici).'],
        };
    }

    /**
     * @param list<SimulatedPronosticLine> $linesAfterSimulate
     * @param array<int, float>          $rawTotals
     *
     * @return array{0: string, 1: int, 2: list<array{team: string, label: string, points: int, delta: ?int, baseline: ?int}>, 3: string}
     */
    private function impactDoubleEquipe(
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
        $delta = $with - $without;
        $rows = [[
            'team' => $placerName,
            'label' => 'Points pronos (équipe)',
            'points' => $with,
            'delta' => 0 !== $delta ? $delta : null,
            'baseline' => 0 !== $delta ? $without : null,
        ]];

        $effect = 0 !== $delta
            ? sprintf(
                '+%d pts pronos grâce au double (total %d, sinon %d sans ce joker).',
                $delta,
                $with,
                $without,
            )
            : sprintf('Total pronos : %d pt (barème standard sans bonus double sur ce score).', $with);

        return [$placerName, $placerId, $rows, $effect];
    }

    /**
     * @param list<SimulatedPronosticLine> $linesAfterSimulate
     * @param array<int, float>          $rawTotals
     *
     * @return array{0: string, 1: int, 2: list<array{team: string, label: string, points: int, delta: ?int, baseline: ?int}>, 3: string}
     */
    private function impactInverseScore(
        int $placerId,
        string $placerName,
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
        $deltaTarget = $with - $without;
        $rows = [[
            'team' => $targetName,
            'label' => 'Points pronos (cible)',
            'points' => $with,
            'delta' => 0 !== $deltaTarget ? $deltaTarget : null,
            'baseline' => 0 !== $deltaTarget ? $without : null,
        ]];

        $effect = sprintf(
            '%s cible %s : %s%d pt sur les pronos de la cible (total %d%s). %s gagne l’avantage relatif.',
            $placerName,
            $targetName,
            $deltaTarget >= 0 ? '+' : '',
            $deltaTarget,
            $with,
            0 !== $deltaTarget ? sprintf(', sinon %d avec pronos normaux', $without) : '',
            $placerName,
        );

        return [$placerName, $placerId, $rows, $effect];
    }

    /**
     * @param array<int, float> $rawTotals
     * @param array<int, float> $stealTotals
     *
     * @return array{0: string, 1: int, 2: list<array{team: string, label: string, points: int, delta: ?int, baseline: ?int}>, 3: string}
     */
    private function impactPiquePoints(
        GameMatch $match,
        int $placerId,
        string $placerName,
        int $targetId,
        string $targetName,
        array $rawTotals,
        array $stealTotals,
    ): array {
        $thiefBefore = (int) round((float) ($rawTotals[$placerId] ?? 0.0));
        $thiefAfter = (int) round((float) ($stealTotals[$placerId] ?? 0.0));
        $victimBefore = (int) round((float) ($rawTotals[$targetId] ?? 0.0));
        $victimAfter = (int) round((float) ($stealTotals[$targetId] ?? 0.0));
        $deltaThief = $thiefAfter - $thiefBefore;
        $deltaVictim = $victimAfter - $victimBefore;

        $stealMap = $this->teamJokerUsageRepository->findPiquePointsTargetsByTeamForMatch($match);
        $mutual = $this->isMutualPique($stealMap, $placerId);

        $rows = [
            [
                'team' => $placerName,
                'label' => 'Points pronos (bénéficiaire)',
                'points' => $thiefAfter,
                'delta' => 0 !== $deltaThief ? $deltaThief : null,
                'baseline' => 0 !== $deltaThief ? $thiefBefore : null,
            ],
            [
                'team' => $targetName,
                'label' => 'Points pronos (cible)',
                'points' => $victimAfter,
                'delta' => 0 !== $deltaVictim ? $deltaVictim : null,
                'baseline' => 0 !== $deltaVictim ? $victimBefore : null,
            ],
        ];

        $effect = $mutual
            ? sprintf(
                'Échange mutuel avec %s : %s%d pt pour le bénéficiaire (%d), %s%d pt pour la cible (%d).',
                $targetName,
                $deltaThief >= 0 ? '+' : '',
                $deltaThief,
                $thiefAfter,
                $deltaVictim >= 0 ? '+' : '',
                $deltaVictim,
                $victimAfter,
            )
            : sprintf(
                '%s récupère les pronos de %s : +%d pt (total %d, était %d) ; la cible passe à %d pt (était %d).',
                $placerName,
                $targetName,
                $deltaThief,
                $thiefAfter,
                $thiefBefore,
                $victimAfter,
                $victimBefore,
            );

        return [$placerName, $placerId, $rows, $effect];
    }

    /**
     * @param array<int, array{base: int, with_joker: int}> $buteurTotals
     *
     * @return array{0: string, 1: int, 2: list<array{team: string, label: string, points: int, delta: ?int, baseline: ?int}>, 3: string}
     */
    private function impactDoubleButeur(int $placerId, string $placerName, array $buteurTotals): array
    {
        $base = (int) ($buteurTotals[$placerId]['base'] ?? 0);
        $with = (int) ($buteurTotals[$placerId]['with_joker'] ?? 0);
        $delta = $with - $base;
        $rows = [[
            'team' => $placerName,
            'label' => 'Points buteurs',
            'points' => $with,
            'delta' => 0 !== $delta ? $delta : null,
            'baseline' => 0 !== $delta ? $base : null,
        ]];

        $effect = 0 !== $delta
            ? sprintf('+%d pts buteurs (×2 sur les buts marqués, total %d au lieu de %d).', $delta, $with, $base)
            : 'Aucun but pour l’instant : le double buteur s’appliquera à chaque but marqué.';

        return [$placerName, $placerId, $rows, $effect];
    }

    /**
     * @param array<int, array{base: int, with_joker: int}> $buteurTotals
     *
     * @return array{0: string, 1: int, 2: list<array{team: string, label: string, points: int, delta: ?int, baseline: ?int}>, 3: string}
     */
    private function impactInverseButeur(
        int $placerId,
        string $placerName,
        int $targetId,
        string $targetName,
        array $buteurTotals,
    ): array {
        $base = (int) ($buteurTotals[$targetId]['base'] ?? 0);
        $with = (int) ($buteurTotals[$targetId]['with_joker'] ?? 0);
        $delta = $with - $base;
        $rows = [[
            'team' => $targetName,
            'label' => 'Points buteurs (cible)',
            'points' => $with,
            'delta' => 0 !== $delta ? $delta : null,
            'baseline' => 0 !== $delta ? $base : null,
        ]];

        $effect = sprintf(
            '%s cible %s : %s%d pt buteurs (total %d%s). %s en profite si la cible marque.',
            $placerName,
            $targetName,
            $delta >= 0 ? '+' : '',
            $delta,
            $with,
            0 !== $delta ? sprintf(', sinon %d sans inversion', $base) : '',
            $placerName,
        );

        return [$placerName, $placerId, $rows, $effect];
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
        string $scoreLabel,
        array $stealTotals,
        array $finalTotals,
        array $teams,
    ): array {
        $collectorName = $this->teamNameById($teams, $collectorId);
        $collecteJoker = $this->jokerRepository->findOneBy(['code' => Joker::CODE_COLLECTE_POINTS]);
        $code = Joker::CODE_COLLECTE_POINTS;
        $deltaCollector = (int) round((float) ($finalTotals[$collectorId] ?? 0.0))
            - (int) round((float) ($stealTotals[$collectorId] ?? 0.0));
        $afterCollector = (int) round((float) ($finalTotals[$collectorId] ?? 0.0));
        $beforeCollector = (int) round((float) ($stealTotals[$collectorId] ?? 0.0));

        $impactRows = [[
            'team' => $collectorName,
            'label' => 'Points pronos (collecte)',
            'points' => $afterCollector,
            'delta' => 0 !== $deltaCollector ? $deltaCollector : null,
            'baseline' => 0 !== $deltaCollector ? $beforeCollector : null,
        ]];

        foreach ($teams as $team) {
            $teamId = (int) ($team->getId() ?? 0);
            if ($teamId <= 0 || $teamId === $collectorId) {
                continue;
            }

            $before = (int) round((float) ($stealTotals[$teamId] ?? 0.0));
            $after = (int) round((float) ($finalTotals[$teamId] ?? 0.0));
            $delta = $after - $before;
            if (0 === $delta) {
                continue;
            }

            $impactRows[] = [
                'team' => (string) $team->getName(),
                'label' => 'Prélèvement 10 %',
                'points' => $after,
                'delta' => $delta,
                'baseline' => $before,
            ];
        }

        return [
            'code' => $code,
            'name' => $collecteJoker instanceof Joker ? $collecteJoker->getDisplayTitle() : 'Collecte de points',
            'icon' => Joker::tablerIconClassForCode($code),
            'image' => $collecteJoker?->getImage(),
            'placer_team' => $collectorName,
            'beneficiary_team' => $collectorName,
            'target_team' => null,
            'neutralized' => false,
            'score_label' => $scoreLabel,
            'summary' => sprintf(
                'Équipe bénéficiaire : %s. Score retenu : %s. Prélève 10 %% des points pronos des autres équipes après les autres jokers (+%d pt ici, total %d).',
                $collectorName,
                $scoreLabel,
                $deltaCollector,
                $afterCollector,
            ),
            'description' => $collecteJoker?->getDescription(),
            'technical_lines' => $collecteJoker instanceof Joker ? $collecteJoker->getTechnicalExplanationLines() : [],
            'impact_rows' => $impactRows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildEspionItem(TeamJokerUsage $usage, string $scoreLabel): array
    {
        $joker = $usage->getJoker();
        $code = Joker::CODE_ESPION;
        $placerName = $this->teamName($usage->getTeam());

        return [
            'code' => $code,
            'name' => $joker instanceof Joker ? $joker->getDisplayTitle() : 'Espion',
            'icon' => Joker::tablerIconClassForCode($code),
            'image' => $joker?->getImage(),
            'placer_team' => $placerName,
            'beneficiary_team' => $placerName,
            'target_team' => null,
            'neutralized' => false,
            'score_label' => $scoreLabel,
            'summary' => sprintf(
                'Équipe bénéficiaire : %s. Score retenu : %s. Renseignements (cotes, jokers posés) — aucun impact sur les points.',
                $placerName,
                $scoreLabel,
            ),
            'description' => $joker?->getDescription(),
            'technical_lines' => $joker instanceof Joker ? $joker->getTechnicalExplanationLines() : [],
            'impact_rows' => [],
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

    /**
     * @param array<int, int> $stealMap
     */
    private function isMutualPique(array $stealMap, int $teamId): bool
    {
        $victimId = $stealMap[$teamId] ?? null;

        return null !== $victimId && ($stealMap[$victimId] ?? null) === $teamId;
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
