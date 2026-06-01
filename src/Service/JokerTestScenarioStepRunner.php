<?php

declare(strict_types=1);

namespace App\Service;

use App\Data\JokerTestScenarioDefinition;
use App\Entity\But;
use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\TeamRankingSnapshot;
use App\Repository\ButeurRepository;
use App\Repository\GameMatchRepository;

/**
 * Avance le scénario jokers étape par étape (coup d'envoi, buts, fin).
 */
final class JokerTestScenarioStepRunner
{
    public function __construct(
        private readonly JokerTestScenarioStateStore $stateStore,
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly ButeurRepository $buteurRepository,
        private readonly TestMatchManualSyncService $testMatchManualSyncService,
    ) {
    }

    /**
     * @return array{ok: bool, step: array<string, mixed>, result?: array<string, mixed>, next_step?: array<string, mixed>|null}
     */
    public function advance(): array
    {
        $state = $this->stateStore->read();
        if (null === $state) {
            throw new \RuntimeException('Scénario non initialisé. Exécutez app:joker-test:setup.');
        }

        $steps = JokerTestScenarioDefinition::steps();
        $index = $state['step_index'];

        if ($index >= \count($steps) - 1) {
            return [
                'ok' => true,
                'step' => $steps[$index],
                'message' => 'Scénario déjà terminé.',
                'next_step' => null,
            ];
        }

        $current = $steps[$index];
        $result = null;

        if ('info' !== ($current['type'] ?? 'info')) {
            $result = $this->executeStep($current, $state);
        }

        $state['step_index'] = $index + 1;
        $this->stateStore->write($state);

        $next = $steps[$state['step_index']] ?? null;

        return [
            'ok' => true,
            'step' => $current,
            'result' => $result,
            'next_step' => $next,
        ];
    }

    public function resetProgress(): void
    {
        $state = $this->stateStore->read();
        if (null === $state) {
            throw new \RuntimeException('Scénario non initialisé.');
        }

        $state['step_index'] = 0;
        $this->stateStore->write($state);

        $this->resetMatchesToScheduled($state['match_ids']);
    }

    /**
     * @param array<string, mixed> $step
     * @param array{
     *     step_index: int,
     *     match_ids: list<int>,
     *     team_ids: array<string, int>,
     *     buteur_ids: array<string, int>,
     *     seeded_at: string
     * } $state
     *
     * @return array<string, mixed>
     */
    private function executeStep(array $step, array $state): array
    {
        $matchIndex = $step['match_index'] ?? null;
        if (!\is_int($matchIndex)) {
            throw new \InvalidArgumentException('Étape sans match_index.');
        }

        $matchId = $state['match_ids'][$matchIndex] ?? null;
        if (!\is_int($matchId)) {
            throw new \InvalidArgumentException(sprintf('Match index %d introuvable dans l’état.', $matchIndex));
        }

        $match = $this->gameMatchRepository->find($matchId);
        if (!$match instanceof GameMatch) {
            throw new \RuntimeException(sprintf('Match #%d introuvable.', $matchId));
        }

        return match ($step['type'] ?? '') {
            'kickoff' => $this->runKickoff($match),
            'goal' => $this->runGoal($match, $step, $state),
            'finish' => $this->runFinish($match, $step),
            default => throw new \InvalidArgumentException(sprintf('Type d’étape inconnu : %s', (string) ($step['type'] ?? ''))),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function runKickoff(GameMatch $match): array
    {
        $this->testMatchManualSyncService->kickoff($match);

        return [
            'statut' => $match->getStatut(),
            'score' => [$match->getScoreDomicile(), $match->getScoreExterieur()],
        ];
    }

    /**
     * @param array<string, mixed> $step
     * @param array{
     *     step_index: int,
     *     match_ids: list<int>,
     *     team_ids: array<string, int>,
     *     buteur_ids: array<string, int>,
     *     seeded_at: string
     * } $state
     *
     * @return array<string, mixed>
     */
    private function runGoal(GameMatch $match, array $step, array $state): array
    {
        $goalKey = $step['goal_key'] ?? null;
        if (!\is_string($goalKey)) {
            throw new \InvalidArgumentException('goal_key manquant.');
        }

        $buteurId = $state['buteur_ids'][$goalKey] ?? null;
        if (!\is_int($buteurId)) {
            throw new \InvalidArgumentException(sprintf('Buteur pour « %s » introuvable.', $goalKey));
        }

        $buteur = $this->buteurRepository->find($buteurId);
        if (null === $buteur) {
            throw new \RuntimeException(sprintf('Buteur #%d introuvable.', $buteurId));
        }

        $minute = match ($goalKey) {
            'm1_goal' => 23,
            'm2_goal_home' => 12,
            'm2_goal_away' => 67,
            'm3_goal' => 55,
            default => 10,
        };

        $but = $this->testMatchManualSyncService->registerGoal($match, $buteur, $minute, false);

        return [
            'but_id' => (int) $but->getId(),
            'score' => [$match->getScoreDomicile(), $match->getScoreExterieur()],
        ];
    }

    /**
     * @param array<string, mixed> $step
     *
     * @return array<string, mixed>
     */
    private function runFinish(GameMatch $match, array $step): array
    {
        $score = $step['score'] ?? null;
        if (!\is_array($score) || 2 !== \count($score)) {
            throw new \InvalidArgumentException('Score final manquant pour finish.');
        }

        $this->testMatchManualSyncService->finish($match, (int) $score[0], (int) $score[1]);

        return [
            'statut' => $match->getStatut(),
            'score' => [$match->getScoreDomicile(), $match->getScoreExterieur()],
        ];
    }

    /**
     * @param list<int> $matchIds
     */
    private function resetMatchesToScheduled(array $matchIds): void
    {
        $em = $this->gameMatchRepository->getEntityManager();

        foreach ($matchIds as $matchId) {
            $match = $this->gameMatchRepository->find($matchId);
            if (!$match instanceof GameMatch) {
                continue;
            }

            foreach ($em->getRepository(But::class)->findBy(['matchRef' => $match]) as $but) {
                $em->remove($but);
            }

            foreach ($em->getRepository(TeamRankingSnapshot::class)->findBy(['matchRef' => $match]) as $snapshot) {
                $em->remove($snapshot);
            }

            foreach ($em->getRepository(Pronostic::class)->findBy(['match' => $match]) as $pronostic) {
                $pronostic->setPoints(null);
                $pronostic->setPointsEquipe(null);
                $pronostic->setPointsBase(null);
            }

            $match
                ->setStatut('SCHEDULED')
                ->setScoreDomicile(null)
                ->setScoreExterieur(null)
                ->setLiveElapsedMinute(null)
                ->setLiveScoresFinalizedAt(null);
        }

        $em->flush();
    }
}
