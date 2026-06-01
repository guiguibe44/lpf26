<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\TeamMember;
use App\Entity\User;

/**
 * Classe les joueurs d’une équipe sur une période (pronos + buteurs).
 */
final class TeamRecapMvpResolver
{
    /**
     * @param list<TeamMember>          $members
     * @param list<GameMatch>           $matches
     * @param array<int, Pronostic>     $pronosticByMatchAndUserId matchId => userId => Pronostic
     * @param array<int, list<array{buteur_id: int, points: int}>> $goalsByMatchId
     *
     * @return list<array{
     *     user_id: int,
     *     nickname: string,
     *     points: int,
     *     pronostic_points: int,
     *     buteur_points: int,
     *     exact_scores: int,
     *     good_results: int
     * }>
     */
    public function rankMembers(
        array $members,
        array $matches,
        array $pronosticByMatchAndUserId,
        array $goalsByMatchId,
    ): array {
        $stats = [];

        foreach ($members as $member) {
            $player = $member->getPlayer();
            if (!$player instanceof User || null === $player->getId()) {
                continue;
            }

            $userId = (int) $player->getId();
            $stats[$userId] = [
                'user_id' => $userId,
                'nickname' => (string) $member->getNickname(),
                'points' => 0,
                'pronostic_points' => 0,
                'buteur_points' => 0,
                'exact_scores' => 0,
                'good_results' => 0,
            ];
        }

        if ([] === $stats) {
            return [];
        }

        $buteurIdByUserId = [];
        foreach ($members as $member) {
            $player = $member->getPlayer();
            $buteur = $player?->getButeurChoisi();
            if ($player instanceof User && null !== $player->getId() && null !== $buteur?->getId()) {
                $buteurIdByUserId[(int) $player->getId()] = (int) $buteur->getId();
            }
        }

        foreach ($matches as $match) {
            $matchId = (int) $match->getId();
            if ($matchId <= 0) {
                continue;
            }

            foreach ($stats as $userId => $_) {
                $prono = $pronosticByMatchAndUserId[$matchId][$userId] ?? null;
                if (!$prono instanceof Pronostic) {
                    continue;
                }

                $pts = (int) round((float) ($prono->getPoints() ?? 0));
                $stats[$userId]['pronostic_points'] += $pts;
                $stats[$userId]['points'] += $pts;

                $base = $prono->getPointsBase();
                if (30 === $base) {
                    ++$stats[$userId]['exact_scores'];
                } elseif (10 === $base) {
                    ++$stats[$userId]['good_results'];
                }
            }

            foreach ($goalsByMatchId[$matchId] ?? [] as $goal) {
                $buteurId = (int) ($goal['buteur_id'] ?? 0);
                $goalPts = (int) ($goal['points'] ?? 0);
                foreach ($buteurIdByUserId as $uid => $chosenButeurId) {
                    if ($chosenButeurId === $buteurId) {
                        $stats[$uid]['buteur_points'] += $goalPts;
                        $stats[$uid]['points'] += $goalPts;
                    }
                }
            }
        }

        $ranked = array_values($stats);
        usort($ranked, static function (array $a, array $b): int {
            if ($b['points'] !== $a['points']) {
                return $b['points'] <=> $a['points'];
            }
            if ($b['exact_scores'] !== $a['exact_scores']) {
                return $b['exact_scores'] <=> $a['exact_scores'];
            }
            if ($b['good_results'] !== $a['good_results']) {
                return $b['good_results'] <=> $a['good_results'];
            }

            return strcmp($a['nickname'], $b['nickname']);
        });

        return $ranked;
    }
}
