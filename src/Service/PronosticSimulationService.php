<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SimulatedPronosticLine;
use App\Entity\GameMatch;
use App\Entity\Pronostic;

final class PronosticSimulationService
{
    public const DEFAULT_POINTS_SCORE_EXACT = 3;
    public const DEFAULT_POINTS_BON_RESULTAT = 1;
    public const DEFAULT_POINTS_MAUVAIS_RESULTAT = 0;
    public const MAX_COTE_COEFFICIENT = 5.0;

    /**
     * @param iterable<Pronostic> $pronostics
     * @param array<int, int>     $playerTeamMap
     * @param array<int, string>  $jokerCodeByTeamId teamId => joker code
     *
     * @return list<SimulatedPronosticLine>
     */
    public function simulate(
        GameMatch $match,
        int $scoreDomicileReel,
        int $scoreExterieurReel,
        iterable $pronostics,
        array $playerTeamMap = [],
        array $playerLabels = [],
        array $jokerCodeByTeamId = [],
        ?JokerScoringApplicator $jokerScoringApplicator = null,
    ): array {
        $pronosticList = [];
        foreach ($pronostics as $pronostic) {
            if ($pronostic instanceof Pronostic) {
                $pronosticList[] = $pronostic;
            }
        }

        $totalPronostics = count($pronosticList);
        $occurrencesByScore = [];
        $riskByPronosticId = [];
        $teamScorePronostics = [];

        foreach ($pronosticList as $pronostic) {
            $pronosticId = $pronostic->getId();
            $home = $pronostic->getScoreDomicile();
            $away = $pronostic->getScoreExterieur();
            if (null === $home || null === $away) {
                continue;
            }

            $scoreKey = sprintf('%d-%d', $home, $away);
            $occurrencesByScore[$scoreKey] = ($occurrencesByScore[$scoreKey] ?? 0) + 1;

            $playerId = $pronostic->getJoueur()?->getId();
            $teamId = null !== $playerId ? ($playerTeamMap[$playerId] ?? null) : null;
            if (null !== $teamId && null !== $pronosticId) {
                $teamScorePronostics[$teamId][$scoreKey][] = $pronosticId;
            }
        }

        foreach ($teamScorePronostics as $scoresByTeam) {
            foreach ($scoresByTeam as $pronosticIds) {
                $isRisk = \count($pronosticIds) >= 2;
                foreach ($pronosticIds as $pronosticId) {
                    $riskByPronosticId[$pronosticId] = $isRisk;
                }
            }
        }

        $lines = [];
        foreach ($pronosticList as $pronostic) {
            $pronosticId = $pronostic->getId();
            $home = $pronostic->getScoreDomicile();
            $away = $pronostic->getScoreExterieur();
            if (null === $home || null === $away || null === $pronosticId) {
                continue;
            }

            $playerId = (int) ($pronostic->getJoueur()?->getId() ?? 0);
            $teamId = $playerTeamMap[$playerId] ?? 0;
            $scoreKey = sprintf('%d-%d', $home, $away);
            $sameScoreCount = max(1, (int) ($occurrencesByScore[$scoreKey] ?? 1));
            $coefficientBrut = $totalPronostics > 0 ? ($totalPronostics / $sameScoreCount) : 1.0;
            $coefficient = round(min($coefficientBrut, self::MAX_COTE_COEFFICIENT), 2);
            $basePoints = $this->computeBasePoints(
                $match,
                $scoreDomicileReel,
                $scoreExterieurReel,
                $home,
                $away,
            );
            $standardPoints = (float) round($basePoints * $coefficient);
            $jokerCode = $teamId > 0 ? ($jokerCodeByTeamId[$teamId] ?? null) : null;
            $jokerPoints = null !== $jokerScoringApplicator
                ? $jokerScoringApplicator->applyForTeam(
                    $jokerCode,
                    $match,
                    $scoreDomicileReel,
                    $scoreExterieurReel,
                    $home,
                    $away,
                    $standardPoints,
                )
                : null;

            if (null !== $jokerPoints) {
                $playerPoints = $jokerPoints['playerPoints'];
                $teamPoints = $jokerPoints['teamPoints'];
            } else {
                $playerPoints = $standardPoints;
                $teamPoints = $standardPoints;
            }

            $lines[] = new SimulatedPronosticLine(
                $pronosticId,
                $teamId,
                $playerLabels[$playerId] ?? 'Joueur',
                $home,
                $away,
                $basePoints,
                $coefficient,
                $playerPoints,
                $riskByPronosticId[$pronosticId] ?? false,
                $teamPoints,
            );
        }

        return $lines;
    }

    public function computeBasePoints(
        GameMatch $match,
        int $scoreDomicileReel,
        int $scoreExterieurReel,
        int $scoreDomicilePronostic,
        int $scoreExterieurPronostic,
    ): int {
        $pointsExact = $match->getPointsScoreExact() ?? self::DEFAULT_POINTS_SCORE_EXACT;
        $pointsBonResultat = $match->getPointsBonResultat() ?? self::DEFAULT_POINTS_BON_RESULTAT;
        $pointsMauvaisResultat = $match->getPointsMauvaisResultat() ?? self::DEFAULT_POINTS_MAUVAIS_RESULTAT;

        if ($scoreDomicilePronostic === $scoreDomicileReel && $scoreExterieurPronostic === $scoreExterieurReel) {
            return $pointsExact;
        }

        if ($this->computeResultat($scoreDomicilePronostic, $scoreExterieurPronostic) === $this->computeResultat($scoreDomicileReel, $scoreExterieurReel)) {
            return $pointsBonResultat;
        }

        return $pointsMauvaisResultat;
    }

    private function computeResultat(int $scoreDomicile, int $scoreExterieur): int
    {
        return $scoreDomicile <=> $scoreExterieur;
    }
}
