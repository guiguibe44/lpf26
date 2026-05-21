<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Enum\MatchCoteMode;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Façade : mode actif (config) + conservation du calcul score exact (LPF26).
 */
final class MatchCoteService
{
    private readonly MatchCoteMode $activeMode;

    public function __construct(
        #[Autowire('%app.match_cote_mode%')]
        string $matchCoteMode,
        private readonly MatchCoteOneNTwoCalculator $oneNTwoCalculator,
        private readonly MatchCoteExactScoreCalculator $exactScoreCalculator,
        private readonly MatchOutcomeResolver $matchOutcomeResolver,
    ) {
        $this->activeMode = MatchCoteMode::fromConfig($matchCoteMode);
    }

    public function getActiveMode(): MatchCoteMode
    {
        return $this->activeMode;
    }

    public function isOneNTwoMode(): bool
    {
        return MatchCoteMode::ONE_N_TWO === $this->activeMode;
    }

    /**
     * @param iterable<Pronostic> $pronostics
     *
     * @return array{
     *     mode: string,
     *     min: ?float,
     *     moyenne: ?float,
     *     max: ?float,
     *     pronostics_count: int,
     *     home: ?float,
     *     draw: ?float,
     *     away: ?float
     * }
     */
    public function computeOverviewForMatch(GameMatch $match, iterable $pronostics): array
    {
        $overview = $this->calculatorFor($this->activeMode)->computeOverview($this->normalizePronostics($pronostics));

        $scoreHome = $match->getScoreDomicile();
        $scoreAway = $match->getScoreExterieur();
        if (null !== $scoreHome && null !== $scoreAway) {
            $overview['active_outcome'] = $this->matchOutcomeResolver->resolve($scoreHome, $scoreAway);
        }

        return $overview;
    }

    /**
     * Aperçu dans l'autre mode (admin / debug).
     *
     * @param iterable<Pronostic> $pronostics
     *
     * @return array{
     *     mode: string,
     *     min: ?float,
     *     moyenne: ?float,
     *     max: ?float,
     *     pronostics_count: int,
     *     home: ?float,
     *     draw: ?float,
     *     away: ?float
     * }
     */
    public function computeOverviewLegacyExactScore(iterable $pronostics): array
    {
        return $this->exactScoreCalculator->computeOverview($this->normalizePronostics($pronostics));
    }

    /**
     * @param iterable<Pronostic> $pronostics
     *
     * @return array{
     *     score_label: string,
     *     for_score: ?float,
     *     for_outcome: ?string,
     *     for_outcome_label: ?string,
     *     min: ?float,
     *     moyenne: ?float,
     *     max: ?float,
     *     pronostics_count: int,
     *     mode: string,
     *     home: ?float,
     *     draw: ?float,
     *     away: ?float
     * }
     */
    public function buildDisplayContext(int $scoreHome, int $scoreAway, iterable $pronostics): array
    {
        $pronosticList = $this->normalizePronostics($pronostics);
        $overview = $this->calculatorFor($this->activeMode)->computeOverview($pronosticList);
        $forScore = $this->calculatorFor($this->activeMode)->coefficientForPredictedScore(
            $scoreHome,
            $scoreAway,
            $pronosticList,
        );
        $outcome = $this->matchOutcomeResolver->resolve($scoreHome, $scoreAway);

        return [
            'score_label' => sprintf('%d-%d', $scoreHome, $scoreAway),
            'for_score' => $forScore,
            'for_outcome' => $outcome,
            'for_outcome_label' => $this->matchOutcomeResolver->label($outcome),
            'min' => $overview['min'],
            'moyenne' => $overview['moyenne'],
            'max' => $overview['max'],
            'pronostics_count' => $overview['pronostics_count'],
            'mode' => $overview['mode'],
            'home' => $overview['home'],
            'draw' => $overview['draw'],
            'away' => $overview['away'],
        ];
    }

    /**
     * @param iterable<Pronostic> $pronostics
     */
    public function persistMatchOdds(GameMatch $match, iterable $pronostics): void
    {
        $this->calculatorFor($this->activeMode)->persistOnMatch($match, $this->normalizePronostics($pronostics));
    }

    /**
     * @param iterable<Pronostic> $pronostics
     */
    public function coefficientForPronosticLine(
        GameMatch $match,
        int $predHome,
        int $predAway,
        ?int $realHome,
        ?int $realAway,
        int $basePoints,
        iterable $pronostics,
    ): ?float {
        $pronosticList = $this->normalizePronostics($pronostics);
        $pointsExact = $match->getPointsScoreExact() ?? PronosticSimulationService::DEFAULT_POINTS_SCORE_EXACT;
        $pointsBon = $match->getPointsBonResultat() ?? PronosticSimulationService::DEFAULT_POINTS_BON_RESULTAT;
        $pointsMauvais = $match->getPointsMauvaisResultat() ?? PronosticSimulationService::DEFAULT_POINTS_MAUVAIS_RESULTAT;

        $matchOdds = MatchCoteMode::ONE_N_TWO === $this->activeMode
            ? $this->oneNTwoCalculator->computeMatchOdds($pronosticList)
            : null;

        return $this->calculatorFor($this->activeMode)->coefficientForPronosticLine(
            $predHome,
            $predAway,
            $realHome,
            $realAway,
            $basePoints,
            $pointsExact,
            $pointsBon,
            $pointsMauvais,
            $pronosticList,
            $matchOdds,
        );
    }

    private function calculatorFor(MatchCoteMode $mode): MatchCoteOneNTwoCalculator|MatchCoteExactScoreCalculator
    {
        return MatchCoteMode::ONE_N_TWO === $mode
            ? $this->oneNTwoCalculator
            : $this->exactScoreCalculator;
    }

    /**
     * @param iterable<Pronostic> $pronostics
     *
     * @return list<Pronostic>
     */
    private function normalizePronostics(iterable $pronostics): array
    {
        $list = [];
        foreach ($pronostics as $pronostic) {
            if ($pronostic instanceof Pronostic) {
                $list[] = $pronostic;
            }
        }

        return $list;
    }
}
