<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Repository\TeamJokerUsageRepository;

/**
 * Joker « inversion score » : les pronostics de l'équipe ciblée sont notés
 * avec domicile / extérieur inversés (ex. 3-0 → 0-3 ; 1-1 inchangé).
 */
final class PronosticScoreInversionService
{
    public function __construct(
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
    ) {
    }

    /**
     * @return array<int, true> teamId => true
     */
    public function getTargetTeamIdsForMatch(GameMatch $match): array
    {
        return $this->teamJokerUsageRepository->findInverseScoreTargetTeamIdsForMatch($match);
    }

    public function isTeamTargeted(int $teamId, array $targetTeamIds): bool
    {
        return isset($targetTeamIds[$teamId]);
    }

    /**
     * @return array{home: int, away: int}
     */
    public function effectiveScores(int $storedHome, int $storedAway, bool $invert): array
    {
        if (!$invert) {
            return ['home' => $storedHome, 'away' => $storedAway];
        }

        return ['home' => $storedAway, 'away' => $storedHome];
    }

    /**
     * @param list<Pronostic>  $pronostics
     * @param array<int, int>  $playerTeamMap
     * @param array<int, true> $targetTeamIds
     *
     * @return array<int, array{home: int, away: int, inverted: bool}>
     */
    public function buildEffectiveScoresByPronosticId(
        array $pronostics,
        array $playerTeamMap,
        array $targetTeamIds,
    ): array {
        $map = [];
        foreach ($pronostics as $pronostic) {
            $pronosticId = $pronostic->getId();
            $home = $pronostic->getScoreDomicile();
            $away = $pronostic->getScoreExterieur();
            if (null === $pronosticId || null === $home || null === $away) {
                continue;
            }

            $playerId = $pronostic->getJoueur()?->getId();
            $teamId = null !== $playerId ? ($playerTeamMap[$playerId] ?? null) : null;
            $invert = null !== $teamId && $this->isTeamTargeted((int) $teamId, $targetTeamIds);
            $effective = $this->effectiveScores($home, $away, $invert);

            $map[(int) $pronosticId] = [
                'home' => $effective['home'],
                'away' => $effective['away'],
                'inverted' => $invert,
            ];
        }

        return $map;
    }
}
