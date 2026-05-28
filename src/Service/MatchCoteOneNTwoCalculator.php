<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Enum\MatchCoteMode;

/**
 * Cotes 1 / N / 2 : popularité par issue, formule proportion + ajustement, plafond ×5, arrondi à 2 décimales.
 */
final class MatchCoteOneNTwoCalculator
{
    public const MAX_COEFFICIENT = 5.0;

    public function __construct(
        private readonly MatchOutcomeResolver $matchOutcomeResolver,
    ) {
    }

    public function mode(): MatchCoteMode
    {
        return MatchCoteMode::ONE_N_TWO;
    }

    /**
     * Formule identique à LPF24 {@see PredictionCalculator::calculateOdds}.
     */
    public function calculateOddsForOutcomeCount(int $totalPredictions, int $countForOutcome): float
    {
        if ($totalPredictions <= 0) {
            return 1.0;
        }

        if ($countForOutcome <= 0) {
            return self::MAX_COEFFICIENT;
        }

        $proportion = $countForOutcome / $totalPredictions;
        $adjustmentFactor = 1 - $proportion;
        $odds = 1 + ((1 / $proportion) - 1) * $adjustmentFactor;
        $odds = min($odds, self::MAX_COEFFICIENT);

        return round($odds, 2);
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
     *     home: ?float,
     *     draw: ?float,
     *     away: ?float,
     *     home_pronos_count: int,
     *     draw_pronos_count: int,
     *     away_pronos_count: int,
     * }
     */
    public function computeOverview(array $pronosticList): array
    {
        $total = \count($pronosticList);
        if (0 === $total) {
            return $this->emptyOverview();
        }

        $counts = $this->countByOutcome($pronosticList);
        $home = $this->calculateOddsForOutcomeCount($total, $counts[MatchOutcomeResolver::OUTCOME_HOME]);
        $draw = $this->calculateOddsForOutcomeCount($total, $counts[MatchOutcomeResolver::OUTCOME_DRAW]);
        $away = $this->calculateOddsForOutcomeCount($total, $counts[MatchOutcomeResolver::OUTCOME_AWAY]);
        $values = [$home, $draw, $away];

        return [
            'mode' => MatchCoteMode::ONE_N_TWO->value,
            'min' => min($values),
            'moyenne' => round(array_sum($values) / 3, 2),
            'max' => max($values),
            'pronostics_count' => $total,
            'home' => $home,
            'draw' => $draw,
            'away' => $away,
            'home_pronos_count' => $counts[MatchOutcomeResolver::OUTCOME_HOME],
            'draw_pronos_count' => $counts[MatchOutcomeResolver::OUTCOME_DRAW],
            'away_pronos_count' => $counts[MatchOutcomeResolver::OUTCOME_AWAY],
        ];
    }

    /**
     * @param list<Pronostic> $pronosticList
     *
     * @return array{HOME: float, DRAW: float, AWAY: float}
     */
    public function computeMatchOdds(array $pronosticList): array
    {
        $overview = $this->computeOverview($pronosticList);

        return [
            MatchOutcomeResolver::OUTCOME_HOME => (float) ($overview['home'] ?? 1.0),
            MatchOutcomeResolver::OUTCOME_DRAW => (float) ($overview['draw'] ?? 1.0),
            MatchOutcomeResolver::OUTCOME_AWAY => (float) ($overview['away'] ?? 1.0),
        ];
    }

    /**
     * @param array{HOME: float, DRAW: float, AWAY: float} $matchOdds
     * @param list<Pronostic>                              $pronosticList
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
        if ([] === $pronosticList) {
            return null;
        }

        $odds = $matchOdds ?? $this->computeMatchOdds($pronosticList);
        $predOutcome = $this->matchOutcomeResolver->resolve($predHome, $predAway);

        if (null === $realHome || null === $realAway) {
            return $odds[$predOutcome] ?? 1.0;
        }

        $realOutcome = $this->matchOutcomeResolver->resolve($realHome, $realAway);

        if ($basePoints === $pointsExact) {
            return $odds[$realOutcome] ?? 1.0;
        }

        if ($basePoints === $pointsBonResultat) {
            if ($realHome === $realAway && $predHome === $predAway) {
                return $odds[MatchOutcomeResolver::OUTCOME_DRAW] ?? 1.0;
            }

            return $odds[$realOutcome] ?? 1.0;
        }

        if ($basePoints === $pointsMauvaisResultat) {
            return $odds[$predOutcome] ?? 1.0;
        }

        return $odds[$predOutcome] ?? 1.0;
    }

    /**
     * @param list<Pronostic> $pronosticList
     */
    public function coefficientForPredictedScore(
        int $scoreHome,
        int $scoreAway,
        array $pronosticList,
    ): ?float {
        $odds = $this->computeMatchOdds($pronosticList);
        $outcome = $this->matchOutcomeResolver->resolve($scoreHome, $scoreAway);

        return $odds[$outcome] ?? null;
    }

    /**
     * @param list<Pronostic> $pronosticList
     */
    public function persistOnMatch(GameMatch $match, array $pronosticList): void
    {
        $overview = $this->computeOverview($pronosticList);
        $match
            ->setCoteDomicile($overview['home'])
            ->setCoteNul($overview['draw'])
            ->setCoteExterieur($overview['away'])
            ->setCoteMin($overview['min'])
            ->setCoteMoyenne($overview['moyenne'])
            ->setCoteMax($overview['max']);
    }

    /**
     * @param list<Pronostic> $pronosticList
     *
     * @return array{HOME: int, DRAW: int, AWAY: int}
     */
    private function countByOutcome(array $pronosticList): array
    {
        $counts = [
            MatchOutcomeResolver::OUTCOME_HOME => 0,
            MatchOutcomeResolver::OUTCOME_DRAW => 0,
            MatchOutcomeResolver::OUTCOME_AWAY => 0,
        ];

        foreach ($pronosticList as $pronostic) {
            $home = $pronostic->getScoreDomicile();
            $away = $pronostic->getScoreExterieur();
            if (null === $home || null === $away) {
                continue;
            }

            $outcome = $this->matchOutcomeResolver->resolve((int) $home, (int) $away);
            ++$counts[$outcome];
        }

        return $counts;
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
     *     away: null,
     *     home_pronos_count: int,
     *     draw_pronos_count: int,
     *     away_pronos_count: int,
     * }
     */
    private function emptyOverview(int $pronosticsCount = 0): array
    {
        return [
            'mode' => MatchCoteMode::ONE_N_TWO->value,
            'min' => null,
            'moyenne' => null,
            'max' => null,
            'pronostics_count' => $pronosticsCount,
            'home' => null,
            'draw' => null,
            'away' => null,
            'home_pronos_count' => 0,
            'draw_pronos_count' => 0,
            'away_pronos_count' => 0,
        ];
    }
}
