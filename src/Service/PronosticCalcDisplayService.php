<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SimulatedPronosticLine;
use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Repository\JokerRepository;
use App\Repository\TeamJokerUsageRepository;

/**
 * Enrichit les lignes de simulation avec les facteurs joker affichés dans le détail du calcul.
 */
final class PronosticCalcDisplayService
{
    public function __construct(
        private readonly JokerScoringApplicator $jokerScoringApplicator,
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly JokerRepository $jokerRepository,
    ) {
    }

    /**
     * @param list<SimulatedPronosticLine> $afterSimulate
     * @param list<SimulatedPronosticLine> $afterSteal
     * @param list<SimulatedPronosticLine> $final
     *
     * @return list<SimulatedPronosticLine>
     */
    public function enrich(
        GameMatch $match,
        int $realHome,
        int $realAway,
        array $afterSimulate,
        array $afterSteal,
        array $final,
    ): array {
        $titlesByCode = $this->buildTitlesByCode();
        $jokerCodesByTeam = $this->teamJokerUsageRepository->findJokerCodesByTeamForMatch($match);
        $rawTotals = $this->sumTeamPointsByTeamId($afterSimulate);
        $stealTotals = $this->sumTeamPointsByTeamId($afterSteal);
        $finalTotals = $this->sumTeamPointsByTeamId($final);
        $stealMap = $this->teamJokerUsageRepository->findPiquePointsTargetsByTeamForMatch($match);
        $collecteIds = $this->teamJokerUsageRepository->findCollecteTeamIdsForMatch($match);
        $mutualTeams = $this->resolveMutualPiqueTeamIds($stealMap);

        $enriched = [];
        foreach ($final as $line) {
            $teamId = $line->teamId;
            $multipliers = $this->buildMultipliers(
                $match,
                $realHome,
                $realAway,
                $line,
                $jokerCodesByTeam[$teamId] ?? null,
                $titlesByCode,
            );
            $notes = $this->buildNotes(
                $teamId,
                $line,
                (float) ($rawTotals[$teamId] ?? 0.0),
                (float) ($stealTotals[$teamId] ?? 0.0),
                (float) ($finalTotals[$teamId] ?? 0.0),
                $stealMap,
                $collecteIds,
                $mutualTeams,
                $titlesByCode,
            );

            $enriched[] = new SimulatedPronosticLine(
                $line->pronosticId,
                $line->teamId,
                $line->playerLabel,
                $line->predHome,
                $line->predAway,
                $line->basePoints,
                $line->coefficient,
                $line->points,
                $line->priseRisque,
                $line->teamPoints,
                $line->scoreInverted,
                $multipliers,
                $notes,
            );
        }

        return $enriched;
    }

    /**
     * @param array<string, string> $titlesByCode
     *
     * @return list<array{factor: string, label: ?string}>
     */
    private function buildMultipliers(
        GameMatch $match,
        int $realHome,
        int $realAway,
        SimulatedPronosticLine $line,
        ?string $jokerCode,
        array $titlesByCode,
    ): array {
        if (Joker::CODE_DOUBLE_EQUIPE !== $jokerCode) {
            return [];
        }

        $label = $titlesByCode[Joker::CODE_DOUBLE_EQUIPE] ?? 'Double équipe';
        $wrong = $this->jokerScoringApplicator->isWrongResult(
            $match,
            $realHome,
            $realAway,
            $line->predHome,
            $line->predAway,
        );

        return [
            [
                'factor' => $wrong ? '−3' : '2',
                'label' => $label,
            ],
        ];
    }

    /**
     * @param array<int, int>   $stealMap
     * @param list<int>         $collecteIds
     * @param array<int, true>  $mutualTeams
     * @param array<string, string> $titlesByCode
     *
     * @return list<string>
     */
    private function buildNotes(
        int $teamId,
        SimulatedPronosticLine $line,
        float $rawTeamTotal,
        float $stealTeamTotal,
        float $finalTeamTotal,
        array $stealMap,
        array $collecteIds,
        array $mutualTeams,
        array $titlesByCode,
    ): array {
        $notes = [];

        if ($line->scoreInverted) {
            $notes[] = $titlesByCode[Joker::CODE_INVERSE_SCORE] ?? 'Inv. score';
        }

        if (abs($stealTeamTotal - $rawTeamTotal) > 0.01 && [] !== $stealMap) {
            $piqueLabel = $titlesByCode[Joker::CODE_PIQUE_POINTS] ?? 'Pique';
            if (isset($mutualTeams[$teamId])) {
                $notes[] = $piqueLabel.' (échange)';
            } elseif (isset($stealMap[$teamId])) {
                $notes[] = $piqueLabel.' (vol)';
            } elseif (\in_array($teamId, array_values($stealMap), true)) {
                $notes[] = $piqueLabel.' (perdu)';
            } else {
                $notes[] = $piqueLabel;
            }
        }

        if (abs($finalTeamTotal - $stealTeamTotal) > 0.01 && [] !== $collecteIds) {
            $collecteLabel = $titlesByCode[Joker::CODE_COLLECTE_POINTS] ?? 'Collecte';
            if (\in_array($teamId, $collecteIds, true)) {
                $notes[] = $collecteLabel.' (+10 %)';
            } else {
                $notes[] = $collecteLabel.' (−10 %)';
            }
        }

        return $notes;
    }

    /**
     * @return array<string, string>
     */
    private function buildTitlesByCode(): array
    {
        $map = [];
        foreach ($this->jokerRepository->findAllOrdered() as $joker) {
            $code = $joker->getCode();
            if (null !== $code && '' !== $code) {
                $map[$code] = $joker->getDisplayTitle();
            }
        }

        return $map;
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
     *
     * @return array<int, true>
     */
    private function resolveMutualPiqueTeamIds(array $stealMap): array
    {
        $mutual = [];
        foreach ($stealMap as $thiefId => $victimId) {
            if (($stealMap[$victimId] ?? null) === $thiefId) {
                $mutual[(int) $thiefId] = true;
                $mutual[(int) $victimId] = true;
            }
        }

        return $mutual;
    }
}
