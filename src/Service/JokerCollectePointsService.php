<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SimulatedPronosticLine;
use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Repository\TeamJokerUsageRepository;

/**
 * Joker « collecte » : après tous les autres jokers sur le match, prélève 10 % (arrondi)
 * des points équipe de chaque autre équipe au profit de l'équipe poseuse.
 */
final class JokerCollectePointsService
{
    public const SHARE_RATE = 0.10;

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
        $collectorIds = $this->teamJokerUsageRepository->findCollecteTeamIdsForMatch($match);
        if ([] === $collectorIds) {
            return;
        }

        $finalTotals = $this->sumRawTeamTotals($pronostics, $playerTeamMap);
        foreach ($collectorIds as $collectorId) {
            $finalTotals = $this->applyLevyForCollector($finalTotals, $collectorId);
        }

        $pronosticsByTeam = $this->groupPronosticsByTeam($pronostics, $playerTeamMap);
        foreach ($pronosticsByTeam as $teamId => $teamPronos) {
            $this->distributeTeamPoints(
                $teamPronos,
                (float) ($finalTotals[$teamId] ?? 0.0),
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
        $collectorIds = $this->teamJokerUsageRepository->findCollecteTeamIdsForMatch($match);
        if ([] === $collectorIds) {
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

        $finalTotals = $rawTotals;
        foreach ($collectorIds as $collectorId) {
            $finalTotals = $this->applyLevyForCollector($finalTotals, $collectorId);
        }

        $adjusted = [];
        foreach ($lines as $line) {
            if ($line->teamId <= 0) {
                $adjusted[] = $line;

                continue;
            }

            $teamLines = $linesByTeam[$line->teamId] ?? [];
            $newTeamTotal = (float) ($finalTotals[$line->teamId] ?? 0.0);
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
                $line->playerPoints,
                $line->priseRisque,
                $newLineTeamPoints,
                $line->scoreInverted,
            );
        }

        return $adjusted;
    }

    /**
     * @param array<int, float> $totals
     *
     * @return array<int, float>
     */
    private function applyLevyForCollector(array $totals, int $collectorId): array
    {
        $final = $totals;
        $collected = 0.0;

        foreach ($totals as $teamId => $total) {
            if ($teamId === $collectorId || $total <= 0) {
                continue;
            }

            $levy = (float) round($total * self::SHARE_RATE);
            if ($levy <= 0) {
                continue;
            }

            $levy = min($levy, $total);
            $final[$teamId] = $total - $levy;
            $collected += $levy;
        }

        if ($collected > 0) {
            $final[$collectorId] = ($totals[$collectorId] ?? 0.0) + $collected;
        }

        return $final;
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
