<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;

/**
 * Calcule les cotes d'un match à partir des pronostics enregistrés (avant ou après coup d'envoi).
 */
final class MatchCotePreviewService
{
    private const MAX_COTE_COEFFICIENT = 5.0;

    /**
     * @param iterable<Pronostic> $pronostics
     *
     * @return array{
     *     min: ?float,
     *     moyenne: ?float,
     *     max: ?float,
     *     pronostics_count: int
     * }
     */
    public function computeForMatch(GameMatch $match, iterable $pronostics): array
    {
        return $this->computeFromPronosticList($this->normalizePronostics($pronostics));
    }

    /**
     * @param list<Pronostic> $pronosticList
     *
     * @return array{
     *     min: ?float,
     *     moyenne: ?float,
     *     max: ?float,
     *     pronostics_count: int
     * }
     */
    private function computeFromPronosticList(array $pronosticList): array
    {
        $total = \count($pronosticList);
        if (0 === $total) {
            return [
                'min' => null,
                'moyenne' => null,
                'max' => null,
                'pronostics_count' => 0,
            ];
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
            $coefficientBrut = $total / $sameScoreCount;
            $coefficients[] = round(min($coefficientBrut, self::MAX_COTE_COEFFICIENT), 2);
        }

        if ([] === $coefficients) {
            return [
                'min' => null,
                'moyenne' => null,
                'max' => null,
                'pronostics_count' => $total,
            ];
        }

        return [
            'min' => round(min($coefficients), 2),
            'moyenne' => round(array_sum($coefficients) / \count($coefficients), 2),
            'max' => round(max($coefficients), 2),
            'pronostics_count' => $total,
        ];
    }

    /**
     * Cote appliquée au score indiqué (total pronos / nb de pronos sur ce score, plafond ×5).
     *
     * @param iterable<Pronostic> $pronostics
     */
    public function coefficientForScore(int $scoreHome, int $scoreAway, iterable $pronostics): ?float
    {
        $pronosticList = $this->normalizePronostics($pronostics);
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

        $coefficientBrut = $total / max(1, $sameScoreCount);

        return round(min($coefficientBrut, self::MAX_COTE_COEFFICIENT), 2);
    }

    /**
     * Contexte d'affichage des cotes pour un score simulé ou réel.
     *
     * @param iterable<Pronostic> $pronostics
     *
     * @return array{
     *     score_label: string,
     *     for_score: ?float,
     *     min: ?float,
     *     moyenne: ?float,
     *     max: ?float,
     *     pronostics_count: int
     * }
     */
    public function buildDisplayContext(int $scoreHome, int $scoreAway, iterable $pronostics): array
    {
        $pronosticList = $this->normalizePronostics($pronostics);
        $overview = $this->computeFromPronosticList($pronosticList);

        return [
            'score_label' => sprintf('%d-%d', $scoreHome, $scoreAway),
            'for_score' => $this->coefficientForScore($scoreHome, $scoreAway, $pronosticList),
            'min' => $overview['min'],
            'moyenne' => $overview['moyenne'],
            'max' => $overview['max'],
            'pronostics_count' => $overview['pronostics_count'],
        ];
    }

    /**
     * @param iterable<Pronostic> $pronostics
     *
     * @return list<Pronostic>
     */
    private function normalizePronostics(iterable $pronostics): array
    {
        $pronosticList = [];
        foreach ($pronostics as $pronostic) {
            if ($pronostic instanceof Pronostic) {
                $pronosticList[] = $pronostic;
            }
        }

        return $pronosticList;
    }
}
