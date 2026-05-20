<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SimulatedPronosticLine;
use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Repository\TeamJokerUsageRepository;

/**
 * Joker « pique de points » : vol des points équipe sur un match, ou inversion si cible mutuelle.
 */
final class JokerStealPointsService
{
    public function __construct(
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
    ) {
    }

    /**
     * @param list<Pronostic>  $pronostics
     * @param array<int, int> $playerTeamMap
     */
    public function applyToPronostics(GameMatch $match, array $pronostics, array $playerTeamMap): void
    {
        $stealMap = $this->teamJokerUsageRepository->findPiquePointsTargetsByTeamForMatch($match);
        if ([] === $stealMap) {
            return;
        }

        $rawTotals = $this->sumRawTeamTotals($pronostics, $playerTeamMap);
        $finalTotals = $this->resolveFinalTeamTotals($rawTotals, $stealMap);

        $pronosticsByTeam = $this->groupPronosticsByTeam($pronostics, $playerTeamMap);
        foreach ($pronosticsByTeam as $teamId => $teamPronos) {
            $this->distributeTeamPoints(
                $teamPronos,
                (float) ($finalTotals[$teamId] ?? $rawTotals[$teamId] ?? 0.0),
            );
        }
    }

    /**
     * @param list<SimulatedPronosticLine> $lines
     *
     * @return list<SimulatedPronosticLine>
     */
    public function adjustSimulatedLines(GameMatch $match, array $lines): array
    {
        $stealMap = $this->teamJokerUsageRepository->findPiquePointsTargetsByTeamForMatch($match);
        if ([] === $stealMap) {
            return $lines;
        }

        $rawTotals = [];
        $linesByTeam = [];
        foreach ($lines as $line) {
            if ($line->teamId <= 0) {
                continue;
            }
            $rawTotals[$line->teamId] = ($rawTotals[$line->teamId] ?? 0.0) + $line->teamPoints;
            $linesByTeam[$line->teamId][] = $line;
        }

        $finalTotals = $this->resolveFinalTeamTotals($rawTotals, $stealMap);

        $adjusted = [];
        foreach ($lines as $line) {
            if ($line->teamId <= 0 || !isset($finalTotals[$line->teamId])) {
                $adjusted[] = $line;

                continue;
            }

            $teamLines = $linesByTeam[$line->teamId] ?? [];
            $newTeamTotal = (float) $finalTotals[$line->teamId];
            $oldTeamTotal = (float) ($rawTotals[$line->teamId] ?? 0.0);
            $newLineTeamPoints = $this->allocateLineShare(
                $line->teamPoints,
                $oldTeamTotal,
                $newTeamTotal,
                \count($teamLines),
            );

            $adjusted[] = new SimulatedPronosticLine(
                $line->pronosticId,
                $line->teamId,
                $line->playerLabel,
                $line->predHome,
                $line->predAway,
                $line->basePoints,
                $line->coefficient,
                $line->points,
                $line->priseRisque,
                $newLineTeamPoints,
                $line->scoreInverted,
                $line->calcMultipliers,
                $line->calcNotes,
            );
        }

        return $adjusted;
    }

    /**
     * @param array<int, float> $rawTotals
     * @param array<int, int>   $stealMap thiefTeamId => victimTeamId
     *
     * @return array<int, float>
     */
    public function resolveFinalTeamTotals(array $rawTotals, array $stealMap): array
    {
        if ([] === $stealMap) {
            return $rawTotals;
        }

        $final = $rawTotals;
        $mutualPairs = $this->findMutualPairs($stealMap);
        $teamsInMutual = [];

        foreach ($mutualPairs as [$teamA, $teamB]) {
            $teamsInMutual[$teamA] = true;
            $teamsInMutual[$teamB] = true;
            $final[$teamA] = (float) ($rawTotals[$teamB] ?? 0.0);
            $final[$teamB] = (float) ($rawTotals[$teamA] ?? 0.0);
        }

        foreach ($stealMap as $thiefId => $victimId) {
            if (isset($teamsInMutual[$thiefId]) || isset($teamsInMutual[$victimId])) {
                continue;
            }

            $victimPoints = (float) ($rawTotals[$victimId] ?? 0.0);
            $final[$thiefId] = (float) ($rawTotals[$thiefId] ?? 0.0) + $victimPoints;
            $final[$victimId] = 0.0;
        }

        return $final;
    }

    /**
     * @param array<int, int> $stealMap
     *
     * @return list<array{0: int, 1: int}>
     */
    private function findMutualPairs(array $stealMap): array
    {
        $pairs = [];
        $seen = [];

        foreach ($stealMap as $thiefId => $victimId) {
            if (($stealMap[$victimId] ?? null) !== $thiefId) {
                continue;
            }

            $a = min($thiefId, $victimId);
            $b = max($thiefId, $victimId);
            $key = $a.'-'.$b;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $pairs[] = [$a, $b];
        }

        return $pairs;
    }

    /**
     * @param list<Pronostic>  $pronostics
     * @param array<int, int> $playerTeamMap
     *
     * @return array<int, float>
     */
    private function sumRawTeamTotals(array $pronostics, array $playerTeamMap): array
    {
        $totals = [];
        foreach ($pronostics as $pronostic) {
            $playerId = $pronostic->getJoueur()?->getId();
            $teamId = null !== $playerId ? ($playerTeamMap[$playerId] ?? null) : null;
            if (null === $teamId) {
                continue;
            }

            $totals[(int) $teamId] = ($totals[(int) $teamId] ?? 0.0) + $pronostic->getEffectiveTeamPoints();
        }

        return $totals;
    }

    /**
     * @param list<Pronostic> $pronostics
     *
     * @return array<int, list<Pronostic>>
     */
    private function groupPronosticsByTeam(array $pronostics, array $playerTeamMap): array
    {
        $groups = [];
        foreach ($pronostics as $pronostic) {
            $playerId = $pronostic->getJoueur()?->getId();
            $teamId = null !== $playerId ? ($playerTeamMap[$playerId] ?? null) : null;
            if (null === $teamId) {
                continue;
            }

            $groups[(int) $teamId][] = $pronostic;
        }

        return $groups;
    }

    /**
     * @param list<Pronostic> $pronosticsForTeam
     */
    private function distributeTeamPoints(array $pronosticsForTeam, float $newTotal): void
    {
        if ([] === $pronosticsForTeam) {
            return;
        }

        if ($newTotal <= 0) {
            foreach ($pronosticsForTeam as $pronostic) {
                $pronostic->setPointsEquipe(0.0);
            }

            return;
        }

        $oldSum = 0.0;
        foreach ($pronosticsForTeam as $pronostic) {
            $oldSum += $pronostic->getEffectiveTeamPoints();
        }

        if ($oldSum <= 0) {
            $share = round($newTotal / \count($pronosticsForTeam), 2);
            foreach ($pronosticsForTeam as $pronostic) {
                $pronostic->setPointsEquipe($share);
            }

            return;
        }

        foreach ($pronosticsForTeam as $pronostic) {
            $weight = $pronostic->getEffectiveTeamPoints() / $oldSum;
            $pronostic->setPointsEquipe(round($newTotal * $weight, 2));
        }
    }

    private function allocateLineShare(
        float $linePoints,
        float $oldTeamTotal,
        float $newTeamTotal,
        int $lineCount,
    ): float {
        if ($newTeamTotal <= 0) {
            return 0.0;
        }

        if ($oldTeamTotal <= 0) {
            return round($newTeamTotal / max(1, $lineCount), 2);
        }

        return round($newTeamTotal * ($linePoints / $oldTeamTotal), 2);
    }
}
