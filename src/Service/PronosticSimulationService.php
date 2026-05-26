<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SimulatedPronosticLine;
use App\Entity\GameMatch;
use App\Entity\Pronostic;

final class PronosticSimulationService
{
    public function __construct(
        private readonly PronosticScoreInversionService $pronosticScoreInversionService,
        private readonly MatchCoteService $matchCoteService,
    ) {
    }

    public const DEFAULT_POINTS_SCORE_EXACT = 30;
    public const DEFAULT_POINTS_BON_RESULTAT = 10;
    public const DEFAULT_POINTS_MAUVAIS_RESULTAT = 0;
    public const MAX_COTE_COEFFICIENT = 5.0;

    /**
     * @param iterable<Pronostic> $pronostics
     * @param array<int, int>     $playerTeamMap
     * @param array<int, string>  $jokerCodeByTeamId teamId => joker code
     * @param array<int, true>    $invertedTargetTeamIds équipes ciblées par inversion score
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
        array $invertedTargetTeamIds = [],
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
        $effectiveByPronosticId = $this->pronosticScoreInversionService->buildEffectiveScoresByPronosticId(
            $pronosticList,
            $playerTeamMap,
            $invertedTargetTeamIds,
        );

        foreach ($pronosticList as $pronostic) {
            $pronosticId = $pronostic->getId();
            if (null === $pronosticId || !isset($effectiveByPronosticId[$pronosticId])) {
                continue;
            }

            $effective = $effectiveByPronosticId[$pronosticId];
            $home = $effective['home'];
            $away = $effective['away'];
            $scoreKey = sprintf('%d-%d', $home, $away);
            $occurrencesByScore[$scoreKey] = ($occurrencesByScore[$scoreKey] ?? 0) + 1;

            $playerId = $pronostic->getJoueur()?->getId();
            $teamId = null !== $playerId ? ($playerTeamMap[$playerId] ?? null) : null;
            if (null !== $teamId) {
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
            if (null === $pronosticId || !isset($effectiveByPronosticId[$pronosticId])) {
                continue;
            }

            $effective = $effectiveByPronosticId[$pronosticId];
            $home = $effective['home'];
            $away = $effective['away'];

            $playerId = (int) ($pronostic->getJoueur()?->getId() ?? 0);
            $teamId = $playerTeamMap[$playerId] ?? 0;
            $basePoints = $this->computeBasePoints(
                $match,
                $scoreDomicileReel,
                $scoreExterieurReel,
                $home,
                $away,
            );
            $coefficient = $this->matchCoteService->coefficientForPronosticLine(
                $match,
                $home,
                $away,
                $scoreDomicileReel,
                $scoreExterieurReel,
                $basePoints,
                $pronosticList,
            ) ?? 1.0;
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
                    $coefficient,
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
                $effective['inverted'],
            );
        }

        return $lines;
    }

    /**
     * Cote moyenne du match (identique à {@see GameMatch::getCoteMoyenne()} après score).
     *
     * @param array<string, int>                          $occurrencesByScore
     * @param array<int, array{home: int, away: int}> $effectiveByPronosticId
     */
    public function computeMatchFinalCote(int $totalPronostics, array $occurrencesByScore, array $effectiveByPronosticId): float
    {
        if ($totalPronostics <= 0 || [] === $effectiveByPronosticId) {
            return 1.0;
        }

        $sum = 0.0;
        $count = 0;
        foreach ($effectiveByPronosticId as $effective) {
            $scoreKey = sprintf('%d-%d', $effective['home'], $effective['away']);
            $sameScoreCount = max(1, (int) ($occurrencesByScore[$scoreKey] ?? 1));
            $coefficientBrut = $totalPronostics / $sameScoreCount;
            $sum += min($coefficientBrut, self::MAX_COTE_COEFFICIENT);
            ++$count;
        }

        return round($sum / max(1, $count), 2);
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
