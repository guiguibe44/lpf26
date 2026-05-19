<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Repository\ButeurRepository;
use App\Repository\GameMatchRepository;

/**
 * Orchestration des étapes du scénario match test (CLI + cron HTTP).
 */
final class TestMatchScenarioRunner
{
    public function __construct(
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly ButeurRepository $buteurRepository,
        private readonly MatchPronosticReminderService $matchPronosticReminderService,
        private readonly MatchPushReminderPlanner $matchPushReminderPlanner,
        private readonly TestMatchManualSyncService $testMatchManualSyncService,
    ) {
    }

    /**
     * @param array{
     *     match_id: int,
     *     step: string,
     *     buteur_id?: int|null,
     *     minute?: int,
     *     score_home?: int|null,
     *     score_away?: int|null,
     *     now?: string|null,
     *     dry_run?: bool,
     *     notify?: bool,
     * } $params
     *
     * @return array<string, mixed>
     */
    public function run(array $params): array
    {
        $match = $this->gameMatchRepository->find($params['match_id']);
        if (!$match instanceof GameMatch) {
            throw new \InvalidArgumentException(sprintf('Match #%d introuvable.', $params['match_id']));
        }

        $step = $params['step'];
        $home = $match->getPaysDomicile()?->getNom() ?? '?';
        $away = $match->getPaysExterieur()?->getNom() ?? '?';

        return match ($step) {
            'info' => $this->stepInfo($match, $home, $away),
            'reset-reminder' => $this->stepResetReminder($match),
            'reminder' => $this->stepReminder($match, $params),
            'kickoff' => $this->stepKickoff($match, $home, $away),
            'goal' => $this->stepGoal($match, $params, $home, $away),
            'finish' => $this->stepFinish($match, $params, $home, $away),
            default => throw new \InvalidArgumentException(sprintf('Étape inconnue : %s', $step)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function stepInfo(GameMatch $match, string $home, string $away): array
    {
        $kickoff = $match->getDateHeure();
        $reminderAt = $kickoff instanceof \DateTimeImmutable
            ? $this->matchPushReminderPlanner->getReminderAt($kickoff)
            : null;

        return [
            'ok' => true,
            'step' => 'info',
            'match_id' => (int) $match->getId(),
            'label' => sprintf('%s vs %s', $home, $away),
            'kickoff' => $kickoff?->format(\DateTimeInterface::ATOM),
            'reminder_due_from' => $reminderAt?->format(\DateTimeInterface::ATOM),
            'push_reminder_sent_at' => $match->getPushReminderSentAt()?->format(\DateTimeInterface::ATOM),
            'statut' => $match->getStatut(),
            'score' => [
                'home' => $match->getScoreDomicile(),
                'away' => $match->getScoreExterieur(),
            ],
            'live_minute' => $match->getLiveElapsedMinute(),
            'finalized_at' => $match->getLiveScoresFinalizedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stepResetReminder(GameMatch $match): array
    {
        $this->testMatchManualSyncService->resetPushReminder($match);

        return ['ok' => true, 'step' => 'reset-reminder', 'match_id' => (int) $match->getId()];
    }

    /**
     * @param array{now?: string|null, dry_run?: bool} $params
     *
     * @return array<string, mixed>
     */
    private function stepReminder(GameMatch $match, array $params): array
    {
        $kickoff = $match->getDateHeure();
        if (!$kickoff instanceof \DateTimeImmutable) {
            throw new \InvalidArgumentException('Match sans date/heure de coup d’envoi.');
        }

        $now = isset($params['now']) && \is_string($params['now']) && '' !== $params['now']
            ? new \DateTimeImmutable($params['now'], new \DateTimeZone(MatchPushReminderPlanner::TIMEZONE))
            : new \DateTimeImmutable();

        $dryRun = $params['dry_run'] ?? false;
        $summary = $this->matchPronosticReminderService->processDueReminders($now, $dryRun);
        $summary['ok'] = true;
        $summary['step'] = 'reminder';
        $summary['simulated_now'] = $now->format(\DateTimeInterface::ATOM);
        $summary['reminder_was_due'] = $this->matchPushReminderPlanner->isReminderDue($kickoff, $now);

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function stepKickoff(GameMatch $match, string $home, string $away): array
    {
        $this->testMatchManualSyncService->kickoff($match);

        return [
            'ok' => true,
            'step' => 'kickoff',
            'match_id' => (int) $match->getId(),
            'label' => sprintf('%s vs %s', $home, $away),
            'statut' => $match->getStatut(),
            'score' => ['home' => $match->getScoreDomicile(), 'away' => $match->getScoreExterieur()],
        ];
    }

    /**
     * @param array{buteur_id?: int|null, minute?: int, notify?: bool} $params
     *
     * @return array<string, mixed>
     */
    private function stepGoal(GameMatch $match, array $params, string $home, string $away): array
    {
        $buteurId = $params['buteur_id'] ?? null;
        if (!\is_int($buteurId) || $buteurId <= 0) {
            throw new \InvalidArgumentException('Paramètre buteur_id requis pour goal.');
        }

        $buteur = $this->buteurRepository->find($buteurId);
        if (null === $buteur) {
            throw new \InvalidArgumentException(sprintf('Buteur #%d introuvable.', $buteurId));
        }

        $minute = $params['minute'] ?? 0;
        $notify = $params['notify'] ?? true;

        $but = $this->testMatchManualSyncService->registerGoal($match, $buteur, $minute, $notify);

        return [
            'ok' => true,
            'step' => 'goal',
            'but_id' => (int) $but->getId(),
            'buteur' => trim(((string) $buteur->getPrenom()).' '.((string) $buteur->getNom())),
            'minute' => $minute,
            'label' => sprintf('%s vs %s', $home, $away),
            'score' => ['home' => $match->getScoreDomicile(), 'away' => $match->getScoreExterieur()],
        ];
    }

    /**
     * @param array{score_home?: int|null, score_away?: int|null} $params
     *
     * @return array<string, mixed>
     */
    private function stepFinish(GameMatch $match, array $params, string $home, string $away): array
    {
        $this->testMatchManualSyncService->finish(
            $match,
            $params['score_home'] ?? null,
            $params['score_away'] ?? null,
        );

        return [
            'ok' => true,
            'step' => 'finish',
            'match_id' => (int) $match->getId(),
            'label' => sprintf('%s vs %s', $home, $away),
            'statut' => $match->getStatut(),
            'score' => ['home' => $match->getScoreDomicile(), 'away' => $match->getScoreExterieur()],
            'finalized_at' => $match->getLiveScoresFinalizedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
