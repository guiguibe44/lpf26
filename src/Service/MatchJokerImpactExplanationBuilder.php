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
     *     rule: string,
     *     neutralized: bool,
     *     consequences: list<array{team: string, text: string}>
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

        if (Joker::CODE_BOUCLIER === $code) {
            $consequences = [[
                'team' => $placerName,
                'text' => 'Protection active : jokers offensifs ciblant cette équipe neutralisés.',
            ]];
        } elseif ($neutralized && JokerDefenseService::isOffensiveAgainstTeam($code)) {
            $consequences = [[
                'team' => $targetName,
                'text' => 'Aucun effet (équipe protégée).',
            ]];
        } else {
            $consequences = $this->buildConsequences(
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
            );
        }

        return [
            'code' => $code,
            'name' => $joker instanceof Joker ? $joker->getDisplayTitle() : $code,
            'icon' => Joker::tablerIconClassForCode($code),
            'image' => $joker?->getImage(),
            'rule' => $this->basicRuleForCode($code),
            'neutralized' => $neutralized,
            'consequences' => $consequences,
        ];
    }

    /**
     * @param array<int, float> $rawTotals
     * @param array<int, float> $stealTotals
     * @param array<int, float> $finalTotals
     * @param array<int, array{base: int, with_joker: int}> $buteurTotals
     *
     * @return list<array{team: string, text: string}>
     */
    private function buildConsequences(
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
            Joker::CODE_DOUBLE_EQUIPE => $this->consequencesDoubleEquipe(
                $placerId,
                $placerName,
                $match,
                $scoreHome,
                $scoreAway,
                $linesAfterSimulate,
                $rawTotals,
            ),
            Joker::CODE_INVERSE_SCORE => $this->consequencesInverseScore(
                $targetId,
                $targetName,
                $match,
                $scoreHome,
                $scoreAway,
                $linesAfterSimulate,
                $rawTotals,
            ),
            Joker::CODE_PIQUE_POINTS => $this->consequencesPiquePoints(
                $match,
                $placerId,
                $placerName,
                $targetId,
                $targetName,
                $rawTotals,
                $stealTotals,
            ),
            Joker::CODE_DOUBLE_BUTEUR => $this->consequencesDoubleButeur($placerId, $placerName, $buteurTotals),
            Joker::CODE_INVERSE_BUTEUR => $this->consequencesInverseButeur($targetId, $targetName, $buteurTotals),
            default => [],
        };
    }

    private function basicRuleForCode(string $code): string
    {
        return match ($code) {
            Joker::CODE_DOUBLE_EQUIPE => 'Double les points pronos de l’équipe (×2 si bon, −3× cote si mauvais).',
            Joker::CODE_PIQUE_POINTS => 'Vole tous les points pronos de l’équipe ciblée.',
            Joker::CODE_INVERSE_SCORE => 'Note les pronos de la cible comme si le score était inversé.',
            Joker::CODE_DOUBLE_BUTEUR => 'Double les points buteur de l’équipe sur ce match.',
            Joker::CODE_INVERSE_BUTEUR => 'Les points buteur de la cible deviennent négatifs.',
            Joker::CODE_COLLECTE_POINTS => 'Prélève 10 % des points pronos des autres équipes.',
            Joker::CODE_BOUCLIER => 'Protège l’équipe : les jokers offensifs qui la ciblent sont annulés.',
            Joker::CODE_ESPION => 'Renseignements avant coup d’envoi, sans effet sur les points.',
            default => 'Joker actif sur ce match.',
        };
    }

    private function formatPointsDelta(?int $delta, string $unit): string
    {
        if (null === $delta || 0 === $delta) {
            return sprintf('0 pt %s sur ce score', $unit);
        }

        return sprintf('%s%d pt %s', $delta > 0 ? '+' : '', $delta, $unit);
    }

    /**
     * @param list<SimulatedPronosticLine> $linesAfterSimulate
     * @param array<int, float>          $rawTotals
     *
     * @return list<array{team: string, text: string}>
     */
    private function consequencesDoubleEquipe(
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

        return [[
            'team' => $placerName,
            'text' => $this->formatPointsDelta($with - $without, 'pronos'),
        ]];
    }

    /**
     * @param list<SimulatedPronosticLine> $linesAfterSimulate
     * @param array<int, float>          $rawTotals
     *
     * @return list<array{team: string, text: string}>
     */
    private function consequencesInverseScore(
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

        return [[
            'team' => $targetName,
            'text' => $this->formatPointsDelta($with - $without, 'pronos'),
        ]];
    }

    /**
     * @param array<int, float> $rawTotals
     * @param array<int, float> $stealTotals
     *
     * @return list<array{team: string, text: string}>
     */
    private function consequencesPiquePoints(
        GameMatch $match,
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

        return [
            ['team' => $placerName, 'text' => $this->formatPointsDelta($deltaThief, 'pronos')],
            ['team' => $targetName, 'text' => $this->formatPointsDelta($deltaVictim, 'pronos')],
        ];
    }

    /**
     * @param array<int, array{base: int, with_joker: int}> $buteurTotals
     *
     * @return list<array{team: string, text: string}>
     */
    private function consequencesDoubleButeur(int $placerId, string $placerName, array $buteurTotals): array
    {
        $base = (int) ($buteurTotals[$placerId]['base'] ?? 0);
        $with = (int) ($buteurTotals[$placerId]['with_joker'] ?? 0);

        return [[
            'team' => $placerName,
            'text' => $this->formatPointsDelta($with - $base, 'buteurs'),
        ]];
    }

    /**
     * @param array<int, array{base: int, with_joker: int}> $buteurTotals
     *
     * @return list<array{team: string, text: string}>
     */
    private function consequencesInverseButeur(int $targetId, string $targetName, array $buteurTotals): array
    {
        $base = (int) ($buteurTotals[$targetId]['base'] ?? 0);
        $with = (int) ($buteurTotals[$targetId]['with_joker'] ?? 0);

        return [[
            'team' => $targetName,
            'text' => $this->formatPointsDelta($with - $base, 'buteurs'),
        ]];
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
        $deltaCollector = (int) round((float) ($finalTotals[$collectorId] ?? 0.0))
            - (int) round((float) ($stealTotals[$collectorId] ?? 0.0));

        $consequences = [[
            'team' => $collectorName,
            'text' => $this->formatPointsDelta($deltaCollector, 'pronos'),
        ]];

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

            $consequences[] = [
                'team' => (string) $team->getName(),
                'text' => $this->formatPointsDelta($delta, 'pronos'),
            ];
        }

        return [
            'code' => $code,
            'name' => $collecteJoker instanceof Joker ? $collecteJoker->getDisplayTitle() : 'Collecte de points',
            'icon' => Joker::tablerIconClassForCode($code),
            'image' => $collecteJoker?->getImage(),
            'rule' => $this->basicRuleForCode($code),
            'neutralized' => false,
            'consequences' => $consequences,
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

        return [
            'code' => $code,
            'name' => $joker instanceof Joker ? $joker->getDisplayTitle() : 'Espion',
            'icon' => Joker::tablerIconClassForCode($code),
            'image' => $joker?->getImage(),
            'rule' => $this->basicRuleForCode($code),
            'neutralized' => false,
            'consequences' => [[
                'team' => $placerName,
                'text' => 'Aucun impact points.',
            ]],
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
