<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Repository\ButRepository;
use App\Repository\GameMatchRepository;

final class MatchLiveFeedService
{
    public function __construct(
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly ButRepository $butRepository,
        private readonly MatchStatusResolver $matchStatusResolver,
        private readonly MatchLiveClockLabelResolver $matchLiveClockLabelResolver,
    ) {
    }

    /**
     * @return array{updated_at: string, matches: list<array<string, mixed>>}
     */
    public function buildFeed(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $liveMatches = [];

        foreach ($this->gameMatchRepository->findCandidatesForLiveDisplay($now) as $candidate) {
            if ($this->matchStatusResolver->isMatchLive($candidate, $now)) {
                $liveMatches[] = $candidate;
            }
        }

        $matchIds = array_values(array_filter(array_map(
            static fn (GameMatch $m): ?int => $m->getId(),
            $liveMatches,
        )));

        $goalsByMatchId = $this->butRepository->findGoalRowsIndexedByMatchIds($matchIds);

        $payload = [];
        foreach ($liveMatches as $match) {
            $matchId = (int) $match->getId();
            $payload[] = [
                'id' => $matchId,
                'score_home' => $match->getScoreDomicile() ?? 0,
                'score_away' => $match->getScoreExterieur() ?? 0,
                'status' => $match->getStatut(),
                'live_clock' => $this->matchLiveClockLabelResolver->resolve($match),
                'goals' => $goalsByMatchId[$matchId] ?? [],
            ];
        }

        return [
            'updated_at' => $now->format(\DateTimeInterface::ATOM),
            'matches' => $payload,
        ];
    }
}
