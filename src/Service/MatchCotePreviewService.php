<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;

/**
 * Aperçu des cotes (Espion, live) — délègue à {@see MatchCoteService}.
 */
final class MatchCotePreviewService
{
    public function __construct(
        private readonly MatchCoteService $matchCoteService,
    ) {
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
    public function computeForMatch(GameMatch $match, iterable $pronostics): array
    {
        return $this->matchCoteService->computeOverviewForMatch($match, $pronostics);
    }

    /**
     * @param iterable<Pronostic> $pronostics
     */
    public function coefficientForScore(int $scoreHome, int $scoreAway, iterable $pronostics): ?float
    {
        $context = $this->matchCoteService->buildDisplayContext($scoreHome, $scoreAway, $pronostics);

        return $context['for_score'];
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
        return $this->matchCoteService->buildDisplayContext($scoreHome, $scoreAway, $pronostics);
    }
}
