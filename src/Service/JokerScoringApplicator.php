<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Joker;

final class JokerScoringApplicator
{
    public const DOUBLE_EQUIPE_WRONG_PENALTY_MULTIPLIER = 3.0;

    public function __construct(
        private readonly PronosticSimulationService $pronosticSimulationService,
    ) {
    }

    /**
     * @return array{playerPoints: float, teamPoints: float}|null null si pas de joker applicable
     */
    public function applyForTeam(
        ?string $jokerCode,
        GameMatch $match,
        int $realHome,
        int $realAway,
        int $predHome,
        int $predAway,
        float $standardPoints,
        float $coefficient = 1.0,
    ): ?array {
        if (null === $jokerCode || '' === $jokerCode) {
            return null;
        }

        return match ($jokerCode) {
            Joker::CODE_DOUBLE_EQUIPE => $this->applyDoubleEquipe(
                $match,
                $realHome,
                $realAway,
                $predHome,
                $predAway,
                $coefficient,
            ),
            default => null,
        };
    }

    /**
     * Double équipe : par joueur, base du barème × cote individuelle × 2 (bons prono) ou −3 × cote (mauvais).
     *
     * @return array{playerPoints: float, teamPoints: float}
     */
    private function applyDoubleEquipe(
        GameMatch $match,
        int $realHome,
        int $realAway,
        int $predHome,
        int $predAway,
        float $coefficient,
    ): array {
        $base = $this->pronosticSimulationService->computeBasePoints(
            $match,
            $realHome,
            $realAway,
            $predHome,
            $predAway,
        );
        $mauvais = $match->getPointsMauvaisResultat() ?? PronosticSimulationService::DEFAULT_POINTS_MAUVAIS_RESULTAT;
        $cote = max(0.0, $coefficient);

        if ($base === $mauvais) {
            $penalty = round(self::DOUBLE_EQUIPE_WRONG_PENALTY_MULTIPLIER * $cote);

            return [
                'playerPoints' => -$penalty,
                'teamPoints' => -$penalty,
            ];
        }

        $points = round(2.0 * $base * $cote);

        return [
            'playerPoints' => $points,
            'teamPoints' => $points,
        ];
    }

    public function isWrongResult(
        GameMatch $match,
        int $realHome,
        int $realAway,
        int $predHome,
        int $predAway,
    ): bool {
        $base = $this->pronosticSimulationService->computeBasePoints(
            $match,
            $realHome,
            $realAway,
            $predHome,
            $predAway,
        );
        $mauvais = $match->getPointsMauvaisResultat() ?? PronosticSimulationService::DEFAULT_POINTS_MAUVAIS_RESULTAT;

        return $base === $mauvais;
    }
}
