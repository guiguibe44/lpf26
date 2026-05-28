<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Pronostic;
use App\Enum\MatchCoteMode;

/**
 * Cotes par score exact : coefficient = total ÷ nb pronos sur le même score, plafond ×5, arrondi à 2 décimales.
 */
final class MatchCoteExactScoreCalculator
{
    public const MAX_COEFFICIENT = 5.0;

    public function mode(): MatchCoteMode
    {
        return MatchCoteMode::EXACT_SCORE;
    }

    /**
     * @param list<Pronostic> $pronosticList
     *
     * @return array{
     *     mode: string,
     *     min: ?float,
     *     moyenne: ?float,
     *     max: ?float,
     *     pronostics_count: int,
     *     home: null,
     *     draw: null,
     *     away: null
     * }
     */
    public function computeOverview(array $pronosticList): array
    {
        $total = \count($pronosticList);
        if (0 === $total) {
            return $this->emptyOverview();
        }

        $occurrencesByScore = [];
        foreach ($pronosticList as $pronostic) {
            $home = $pronostic->getScoreDomicile();
            $away = $pronostic->getScoreExterieur();
            if (null === $home || null === $away) {
                continue;
            }

            $scoreKey = sprintf('%d-%d', $home, $away);
            $occurrencesByScore[$scoreKey] = ($occurrencesByScore[$scoreKey] ?? 0) + 1;
        }

        $coefficients = [];
        foreach ($pronosticList as $pronostic) {
            $home = $pronostic->getScoreDomicile();
            $away = $pronostic->getScoreExterieur();
            if (null === $home || null === $away) {
                continue;
            }

            $scoreKey = sprintf('%d-%d', $home, $away);
            $sameScoreCount = max(1, (int) ($occurrencesByScore[$scoreKey] ?? 1));
            $coefficients[] = round(min($total / $sameScoreCount, self::MAX_COEFFICIENT), 2);
        }

        if ([] === $coefficients) {
            return $this->emptyOverview($total);
        }

        return [
            'mode' => MatchCoteMode::EXACT_SCORE->value,
            'min' => round(min($coefficients), 2),
            'moyenne' => round(array_sum($coefficients) / \count($coefficients), 2),
            'max' => round(max($coefficients), 2),
            'pronostics_count' => $total,
            'home' => null,
            'draw' => null,
            'away' => null,
        ];
    }

    /**
     * @param list<Pronostic> $pronosticList
     */
    public function coefficientForPredictedScore(
        int $scoreHome,
        int $scoreAway,
        array $pronosticList,
    ): ?float {
        $total = \count($pronosticList);
        if (0 === $total) {
            return null;
        }

        $sameScoreCount = 0;
        foreach ($pronosticList as $pronostic) {
            if ($scoreHome === (int) $pronostic->getScoreDomicile()
                && $scoreAway === (int) $pronostic->getScoreExterieur()) {
                ++$sameScoreCount;
            }
        }

        return round(min($total / max(1, $sameScoreCount), self::MAX_COEFFICIENT), 2);
    }

    /**
     * @param list<Pronostic> $pronosticList
     */
    public function coefficientForPronosticLine(
        int $predHome,
        int $predAway,
        ?int $realHome,
        ?int $realAway,
        int $basePoints,
        int $pointsExact,
        int $pointsBonResultat,
        int $pointsMauvaisResultat,
        array $pronosticList,
        ?array $matchOdds = null,
    ): ?float {
        unset($realHome, $realAway, $basePoints, $pointsExact, $pointsBonResultat, $pointsMauvaisResultat, $matchOdds);

        return $this->coefficientForPredictedScore($predHome, $predAway, $pronosticList);
    }

    /**
     * @param list<Pronostic> $pronosticList
     */
    public function persistOnMatch(\App\Entity\GameMatch $match, array $pronosticList): void
    {
        $overview = $this->computeOverview($pronosticList);
        $match
            ->setCoteDomicile(null)
            ->setCoteNul(null)
            ->setCoteExterieur(null)
            ->setCoteMin($overview['min'])
            ->setCoteMoyenne($overview['moyenne'])
            ->setCoteMax($overview['max']);
    }

    /**
     * @return array{
     *     mode: string,
     *     min: null,
     *     moyenne: null,
     *     max: null,
     *     pronostics_count: int,
     *     home: null,
     *     draw: null,
     *     away: null
     * }
     */
    private function emptyOverview(int $pronosticsCount = 0): array
    {
        return [
            'mode' => MatchCoteMode::EXACT_SCORE->value,
            'min' => null,
            'moyenne' => null,
            'max' => null,
            'pronostics_count' => $pronosticsCount,
            'home' => null,
            'draw' => null,
            'away' => null,
        ];
    }
}
