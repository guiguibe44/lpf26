<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Repository\GameMatchRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Synchro automatique pendant les matchs : score, statut, buts, finalisation des points.
 */
final class LiveMatchSyncService
{
    public function __construct(
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly Wc2026SyncService $wc2026SyncService,
        private readonly ApiFootballClient $apiFootballClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly bool $liveSyncEnabled = true,
        private readonly int $lookaheadMinutes = 20,
        private readonly int $graceAfterKickoffMinutes = 240,
    ) {
    }

    /**
     * @return array{
     *     matches_checked:int,
     *     matches_synced:int,
     *     goals_created:int,
     *     matches_finalized:int,
     *     api_calls:int,
     *     errors:list<string>
     * }
     */
    public function syncActiveMatches(): array
    {
        if (!$this->liveSyncEnabled) {
            return [
                'matches_checked' => 0,
                'matches_synced' => 0,
                'goals_created' => 0,
                'matches_finalized' => 0,
                'api_calls' => 0,
                'errors' => [],
            ];
        }

        if (!$this->apiFootballClient->isConfigured()) {
            return [
                'matches_checked' => 0,
                'matches_synced' => 0,
                'goals_created' => 0,
                'matches_finalized' => 0,
                'api_calls' => 0,
                'errors' => ['API_FOOTBALL_KEY absente ou vide.'],
            ];
        }

        $now = new \DateTimeImmutable();
        $matches = $this->gameMatchRepository->findForApiLiveSync(
            $now,
            $this->lookaheadMinutes,
            $this->graceAfterKickoffMinutes,
        );

        $matchesChecked = \count($matches);
        $matchesSynced = 0;
        $goalsCreated = 0;
        $matchesFinalized = 0;
        $apiCalls = 0;
        $errors = [];

        foreach ($matches as $index => $match) {
            if ($index > 0) {
                $this->apiFootballClient->applyInterRequestDelay();
            }

            try {
                $this->wc2026SyncService->syncMatchFromApi($match);
                ++$apiCalls;

                $this->apiFootballClient->applyInterRequestDelay();
                $goals = $this->wc2026SyncService->syncGoalsForMatch($match);
                ++$apiCalls;
                $goalsCreated += $goals['created'];

                ++$matchesSynced;

                if ($this->shouldFinalizeMatch($match)) {
                    $this->wc2026SyncService->finalizeMatchAfterFullTime($match);
                    ++$matchesFinalized;
                }
            } catch (\Throwable $e) {
                $errors[] = sprintf('Match #%d : %s', (int) $match->getId(), $e->getMessage());
            }
        }

        $this->entityManager->flush();

        return [
            'matches_checked' => $matchesChecked,
            'matches_synced' => $matchesSynced,
            'goals_created' => $goalsCreated,
            'matches_finalized' => $matchesFinalized,
            'api_calls' => $apiCalls,
            'errors' => $errors,
        ];
    }

    private function shouldFinalizeMatch(GameMatch $match): bool
    {
        if (null !== $match->getLiveScoresFinalizedAt()) {
            return false;
        }

        if (!\in_array($match->getStatut(), ['FINISHED', 'CANCELLED'], true)) {
            return false;
        }

        return null !== $match->getScoreDomicile() && null !== $match->getScoreExterieur();
    }
}
