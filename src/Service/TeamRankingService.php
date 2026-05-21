<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Buteur;
use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\TeamRankingSnapshot;
use App\Entity\Team;
use App\Repository\ButRepository;
use App\Repository\GameMatchRepository;
use App\Repository\PronosticRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\UserRepository;
use App\Repository\TeamRankingSnapshotRepository;
use App\Repository\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TeamRankingService
{
    public function __construct(
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly PronosticRepository $pronosticRepository,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly TeamRepository $teamRepository,
        private readonly TeamRankingSnapshotRepository $teamRankingSnapshotRepository,
        private readonly UserRepository $userRepository,
        private readonly ButRepository $butRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ButeurJokerPointsService $buteurJokerPointsService,
        private readonly PronosticScoreInversionService $pronosticScoreInversionService,
    ) {
    }

    public function saveSnapshotAfterMatch(GameMatch $match): void
    {
        $this->computeAndPersistSnapshotAfterMatch($match);
        $this->entityManager->flush();
    }

    public function rebuildSnapshotsFromMatch(GameMatch $match): void
    {
        $matchDate = $match->getDateHeure();
        if (!$matchDate instanceof \DateTimeImmutable) {
            return;
        }

        $this->teamRankingSnapshotRepository->deleteForMatchesFromDate($matchDate);

        $matches = $this->gameMatchRepository->findMatchesFromDate($matchDate);
        foreach ($matches as $candidate) {
            if (null === $candidate->getScoreDomicile() || null === $candidate->getScoreExterieur()) {
                continue;
            }

            $this->computeAndPersistSnapshotAfterMatch($candidate);
        }

        $this->entityManager->flush();
    }

    private function computeAndPersistSnapshotAfterMatch(GameMatch $match): void
    {
        if (null === $match->getScoreDomicile() || null === $match->getScoreExterieur()) {
            return;
        }

        $teams = $this->teamRepository->findAll();
        if ([] === $teams) {
            return;
        }

        $playerTeamMap = $this->teamMemberRepository->findPlayerTeamMap();
        $scoredPronostics = $this->pronosticRepository->findScoredPronosticsWithTeamMembers();
        $riskStatsByTeamId = $this->computePriseRisqueStatsByTeamId($scoredPronostics, $playerTeamMap);

        $statsByTeamId = [];
        foreach ($teams as $team) {
            $teamId = $team->getId();
            if (null === $teamId) {
                continue;
            }

            $statsByTeamId[$teamId] = [
                'team' => $team,
                'totalPoints' => 0.0,
                'scoresExacts' => 0,
                'bonsResultats' => 0,
                'prisesRisque' => $riskStatsByTeamId[$teamId]['tentees'] ?? 0,
                'prisesRisqueReussies' => $riskStatsByTeamId[$teamId]['reussies'] ?? 0,
                'resultatsFaux' => 0,
            ];
        }

        foreach ($scoredPronostics as $pronostic) {
            $playerId = $pronostic->getJoueur()?->getId();
            if (null === $playerId) {
                continue;
            }

            $teamId = $playerTeamMap[$playerId] ?? null;
            if (null === $teamId || !isset($statsByTeamId[$teamId])) {
                continue;
            }

            $statsByTeamId[$teamId]['totalPoints'] += $pronostic->getEffectiveTeamPoints();

            if ($this->isExactScore($pronostic)) {
                ++$statsByTeamId[$teamId]['scoresExacts'];
            } elseif ($this->isGoodResult($pronostic)) {
                ++$statsByTeamId[$teamId]['bonsResultats'];
            } else {
                ++$statsByTeamId[$teamId]['resultatsFaux'];
            }
        }

        foreach ($this->userRepository->findActivePlayersWithButeur() as $player) {
            $playerId = $player->getId();
            $buteur = $player->getButeurChoisi();
            if (null === $playerId || !$buteur instanceof Buteur) {
                continue;
            }

            $teamId = $playerTeamMap[$playerId] ?? null;
            if (null === $teamId || !isset($statsByTeamId[$teamId])) {
                continue;
            }

            $team = $statsByTeamId[$teamId]['team'];
            if (!$team instanceof Team) {
                continue;
            }

            $statsByTeamId[$teamId]['totalPoints'] += $this->buteurJokerPointsService->sumEffectivePointsForButeur($team, $buteur);
        }

        $stats = array_values($statsByTeamId);
        usort(
            $stats,
            static function (array $a, array $b): int {
                return $b['totalPoints'] <=> $a['totalPoints']
                    ?: $b['scoresExacts'] <=> $a['scoresExacts']
                    ?: $b['bonsResultats'] <=> $a['bonsResultats']
                    ?: $b['prisesRisque'] <=> $a['prisesRisque']
                    ?: strcmp((string) $a['team']->getName(), (string) $b['team']->getName());
            }
        );

        foreach ($stats as $index => $row) {
            $team = $row['team'];
            $snapshot = $this->teamRankingSnapshotRepository->findOneByMatchAndTeam($match, $team)
                ?? (new TeamRankingSnapshot())
                    ->setMatchRef($match)
                    ->setTeam($team);

            $snapshot
                ->setPosition($index + 1)
                ->setTotalPoints((float) round((float) $row['totalPoints']))
                ->setScoresExacts((int) $row['scoresExacts'])
                ->setBonsResultats((int) $row['bonsResultats'])
                ->setPrisesRisque((int) $row['prisesRisque'])
                ->setPrisesRisqueReussies((int) $row['prisesRisqueReussies'])
                ->setResultatsFaux((int) $row['resultatsFaux']);

            $this->entityManager->persist($snapshot);
        }

    }

    /**
     * Prise de risque = au moins 2 pronos de la même équipe avec le même score effectif sur un match.
     *
     * @param iterable<Pronostic> $pronostics
     * @param array<int, int>     $playerTeamMap
     *
     * @return array<int, array{tentees: int, reussies: int}>
     */
    private function computePriseRisqueStatsByTeamId(iterable $pronostics, array $playerTeamMap): array
    {
        $pronosticsByMatchId = [];
        $matchesById = [];

        foreach ($pronostics as $pronostic) {
            $match = $pronostic->getMatch();
            if (!$match instanceof GameMatch) {
                continue;
            }

            $matchId = $match->getId();
            if (null === $matchId) {
                continue;
            }

            $pronosticsByMatchId[$matchId][] = $pronostic;
            $matchesById[$matchId] = $match;
        }

        $statsByTeamId = [];

        foreach ($pronosticsByMatchId as $matchId => $matchPronostics) {
            $match = $matchesById[$matchId];
            $realHome = $match->getScoreDomicile();
            $realAway = $match->getScoreExterieur();
            if (null === $realHome || null === $realAway) {
                continue;
            }

            $invertedTargetTeamIds = $this->pronosticScoreInversionService->getTargetTeamIdsForMatch($match);
            $effectiveByPronosticId = $this->pronosticScoreInversionService->buildEffectiveScoresByPronosticId(
                $matchPronostics,
                $playerTeamMap,
                $invertedTargetTeamIds,
            );

            /** @var array<int, array<string, int>> $countByTeamAndScore */
            $countByTeamAndScore = [];

            foreach ($matchPronostics as $pronostic) {
                $pronosticId = $pronostic->getId();
                if (null === $pronosticId || !isset($effectiveByPronosticId[$pronosticId])) {
                    continue;
                }

                $playerId = $pronostic->getJoueur()?->getId();
                $teamId = null !== $playerId ? ($playerTeamMap[$playerId] ?? null) : null;
                if (null === $teamId) {
                    continue;
                }

                $effective = $effectiveByPronosticId[$pronosticId];
                $scoreKey = sprintf('%d-%d', $effective['home'], $effective['away']);
                $countByTeamAndScore[$teamId][$scoreKey] = ($countByTeamAndScore[$teamId][$scoreKey] ?? 0) + 1;
            }

            foreach ($countByTeamAndScore as $teamId => $scoresOnMatch) {
                foreach ($scoresOnMatch as $scoreKey => $sameScoreCount) {
                    if ($sameScoreCount < 2) {
                        continue;
                    }

                    if (!isset($statsByTeamId[$teamId])) {
                        $statsByTeamId[$teamId] = ['tentees' => 0, 'reussies' => 0];
                    }

                    ++$statsByTeamId[$teamId]['tentees'];

                    if (!preg_match('/^(\d+)-(\d+)$/', $scoreKey, $matches)) {
                        continue;
                    }

                    $predHome = (int) $matches[1];
                    $predAway = (int) $matches[2];
                    if ($this->isSuccessfulPrediction($realHome, $realAway, $predHome, $predAway)) {
                        ++$statsByTeamId[$teamId]['reussies'];
                    }
                }
            }
        }

        return $statsByTeamId;
    }

    private function isSuccessfulPrediction(int $realHome, int $realAway, int $predHome, int $predAway): bool
    {
        if ($realHome === $predHome && $realAway === $predAway) {
            return true;
        }

        return ($predHome <=> $predAway) === ($realHome <=> $realAway);
    }

    private function isExactScore(Pronostic $pronostic): bool
    {
        $match = $pronostic->getMatch();
        if (!$match instanceof GameMatch) {
            return false;
        }

        $realHome = $match->getScoreDomicile();
        $realAway = $match->getScoreExterieur();
        $predHome = $pronostic->getScoreDomicile();
        $predAway = $pronostic->getScoreExterieur();

        return null !== $realHome
            && null !== $realAway
            && null !== $predHome
            && null !== $predAway
            && $realHome === $predHome
            && $realAway === $predAway;
    }

    private function isGoodResult(Pronostic $pronostic): bool
    {
        $match = $pronostic->getMatch();
        if (!$match instanceof GameMatch || $this->isExactScore($pronostic)) {
            return false;
        }

        $realHome = $match->getScoreDomicile();
        $realAway = $match->getScoreExterieur();
        $predHome = $pronostic->getScoreDomicile();
        $predAway = $pronostic->getScoreExterieur();

        if (null === $realHome || null === $realAway || null === $predHome || null === $predAway) {
            return false;
        }

        return ($predHome <=> $predAway) === ($realHome <=> $realAway);
    }
}
