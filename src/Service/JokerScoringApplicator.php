<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Joker;

final class JokerScoringApplicator
{
    public const DOUBLE_EQUIPE_WRONG_PENALTY = -5.0;

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
                $standardPoints,
            ),
            default => null,
        };
    }

    /**
     * @return array{playerPoints: float, teamPoints: float}
     */
    private function applyDoubleEquipe(
        GameMatch $match,
        int $realHome,
        int $realAway,
        int $predHome,
        int $predAway,
        float $standardPoints,
    ): array {
        if ($this->isWrongResult($match, $realHome, $realAway, $predHome, $predAway)) {
            return [
                'playerPoints' => 0.0,
                'teamPoints' => self::DOUBLE_EQUIPE_WRONG_PENALTY,
            ];
        }

        return [
            'playerPoints' => 0.0,
            'teamPoints' => round(2.0 * $standardPoints),
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
