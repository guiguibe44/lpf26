<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\KdoMatchWinnerResult;
use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\Team;
use App\Repository\GameMatchRepository;
use App\Repository\PronosticRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRankingSnapshotRepository;
use App\Repository\TeamRepository;

final class KdoMatchWinnerService
{
    public function __construct(
        private readonly PronosticRepository $pronosticRepository,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly TeamRepository $teamRepository,
        private readonly TeamRankingSnapshotRepository $teamRankingSnapshotRepository,
        private readonly GameMatchRepository $gameMatchRepository,
    ) {
    }

    public function resolveWinner(GameMatch $match): ?KdoMatchWinnerResult
    {
        if (!$match->isKdoMatch() || !$this->isMatchFinished($match)) {
            return null;
        }

        $exactCountsByTeamId = $this->countExactScoresByTeam($match);
        if ([] === $exactCountsByTeamId) {
            return null;
        }

        $maxExact = max($exactCountsByTeamId);
        if ($maxExact < 1) {
            return null;
        }

        $candidateTeamIds = array_keys(array_filter(
            $exactCountsByTeamId,
            static fn (int $count): bool => $count === $maxExact,
        ));

        $winnerTeam = $this->pickWinnerAmongCandidates($match, $candidateTeamIds);

        return new KdoMatchWinnerResult($winnerTeam, $maxExact);
    }

    /**
     * @return array<int, int> teamId => nombre de scores exacts sur le match
     */
    public function countExactScoresByTeam(GameMatch $match): array
    {
        if (!$this->isMatchFinished($match)) {
            return [];
        }

        $playerTeamMap = $this->teamMemberRepository->findPlayerTeamMap();
        $counts = [];

        foreach ($this->pronosticRepository->findByMatchWithTeamMembers($match) as $pronostic) {
            if (!$this->isExactScore($pronostic)) {
                continue;
            }

            $playerId = $pronostic->getJoueur()?->getId();
            $teamId = null !== $playerId ? ($playerTeamMap[$playerId] ?? null) : null;
            if (null === $teamId) {
                continue;
            }

            $counts[$teamId] = ($counts[$teamId] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param list<int> $candidateTeamIds
     */
    private function pickWinnerAmongCandidates(GameMatch $match, array $candidateTeamIds): Team
    {
        if (1 === count($candidateTeamIds)) {
            return $this->getTeamById($candidateTeamIds[0]);
        }

        $positionByTeamId = $this->buildPositionMapBeforeMatch($match);
        $teams = array_map(fn (int $teamId): Team => $this->getTeamById($teamId), $candidateTeamIds);

        usort(
            $teams,
            static function (Team $a, Team $b) use ($positionByTeamId): int {
                $posA = $positionByTeamId[(int) $a->getId()] ?? PHP_INT_MAX;
                $posB = $positionByTeamId[(int) $b->getId()] ?? PHP_INT_MAX;

                return $posB <=> $posA
                    ?: strcmp((string) $a->getName(), (string) $b->getName());
            },
        );

        return $teams[0];
    }

    /**
     * @return array<int, int> teamId => position au classement juste avant le match
     */
    private function buildPositionMapBeforeMatch(GameMatch $match): array
    {
        $previousMatch = $this->gameMatchRepository->findLastScoredMatchBefore($match);
        if (!$previousMatch instanceof GameMatch) {
            return [];
        }

        $map = [];
        foreach ($this->teamRankingSnapshotRepository->findRankingForMatch($previousMatch) as $snapshot) {
            $teamId = $snapshot->getTeam()?->getId();
            if (null !== $teamId) {
                $map[(int) $teamId] = $snapshot->getPosition();
            }
        }

        return $map;
    }

    private function getTeamById(int $teamId): Team
    {
        $team = $this->teamRepository->find($teamId);
        if (!$team instanceof Team) {
            throw new \RuntimeException(sprintf('Equipe #%d introuvable.', $teamId));
        }

        return $team;
    }

    private function isMatchFinished(GameMatch $match): bool
    {
        return null !== $match->getScoreDomicile() && null !== $match->getScoreExterieur();
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
}
