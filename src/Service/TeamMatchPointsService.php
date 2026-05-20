<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Buteur;
use App\Entity\GameMatch;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\PronosticRepository;

final class TeamMatchPointsService
{
    public function __construct(
        private readonly PronosticRepository $pronosticRepository,
    ) {
    }

    /**
     * Total des points équipe par match (pronos + buteurs), indexé par id de match.
     *
     * @param iterable<GameMatch>           $matches
     * @param array<int, list<array{buteur_id: int, points: int}>> $goalsByMatchId
     *
     * @return array<int, int>
     */
    public function buildPointsByMatchIdForTeam(Team $team, iterable $matches, array $goalsByMatchId = []): array
    {
        $teamId = $team->getId();
        if (null === $teamId) {
            return [];
        }

        $playerIds = [];
        $teamButeurIds = [];
        foreach ($team->getMembers() as $member) {
            $player = $member->getPlayer();
            if (!$player instanceof User || null === $player->getId()) {
                continue;
            }

            $playerIds[] = (int) $player->getId();

            $buteur = $player->getButeurChoisi();
            if ($buteur instanceof Buteur && null !== $buteur->getId()) {
                $teamButeurIds[(int) $buteur->getId()] = true;
            }
        }

        if ([] === $playerIds) {
            return [];
        }

        $matchList = [];
        foreach ($matches as $match) {
            if ($match instanceof GameMatch && null !== $match->getId()) {
                $matchList[] = $match;
            }
        }

        if ([] === $matchList) {
            return [];
        }

        $pronosticPointsByMatchId = $this->pronosticRepository->sumContributionPointsByMatchForPlayers(
            $playerIds,
            $matchList,
        );

        $totals = [];
        foreach ($matchList as $match) {
            $matchId = (int) $match->getId();
            $pronosticPoints = (int) round($pronosticPointsByMatchId[$matchId] ?? 0.0);
            $buteurPoints = 0;

            foreach ($goalsByMatchId[$matchId] ?? [] as $goal) {
                $buteurId = (int) ($goal['buteur_id'] ?? 0);
                if ($buteurId > 0 && isset($teamButeurIds[$buteurId])) {
                    $buteurPoints += (int) ($goal['points'] ?? 0);
                }
            }

            $totals[$matchId] = $pronosticPoints + $buteurPoints;
        }

        return $totals;
    }
}
