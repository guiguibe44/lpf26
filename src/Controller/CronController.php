<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LiveMatchSyncService;
use App\Service\MatchPronosticReminderService;
use App\Service\TeamRecapEmailService;
use App\Service\TestMatchScenarioRunner;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Déclenchement HTTP des tâches planifiées (cron-job.org, etc.).
 * Protégé par CRON_SECRET dans .env.local (paramètre ?token=…).
 */
#[Route('/cron')]
final class CronController extends AbstractController
{
    public function __construct(
        private readonly ?string $cronSecret,
        private readonly LiveMatchSyncService $liveMatchSyncService,
        private readonly MatchPronosticReminderService $matchPronosticReminderService,
        private readonly TeamRecapEmailService $teamRecapEmailService,
        private readonly TestMatchScenarioRunner $testMatchScenarioRunner,
    ) {
    }

    #[Route('/live-match-sync', name: 'cron_live_match_sync', methods: ['GET', 'POST'])]
    public function liveMatchSync(Request $request): JsonResponse
    {
        $denied = $this->denyUnlessValidToken($request);
        if (null !== $denied) {
            return $denied;
        }

        return $this->json($this->liveMatchSyncService->syncActiveMatches());
    }

    #[Route('/pronostic-reminders', name: 'cron_pronostic_reminders', methods: ['GET', 'POST'])]
    public function pronosticReminders(Request $request): JsonResponse
    {
        $denied = $this->denyUnlessValidToken($request);
        if (null !== $denied) {
            return $denied;
        }

        return $this->json($this->matchPronosticReminderService->processDueReminders());
    }

    #[Route('/team-recap', name: 'cron_team_recap', methods: ['GET', 'POST'])]
    public function teamRecap(Request $request): JsonResponse
    {
        $denied = $this->denyUnlessValidToken($request);
        if (null !== $denied) {
            return $denied;
        }

        $dryRun = filter_var($request->query->get('dry_run', false), \FILTER_VALIDATE_BOOL);
        $force = filter_var($request->query->get('force', false), \FILTER_VALIDATE_BOOL);

        return $this->json($this->teamRecapEmailService->process(dryRun: $dryRun, force: $force));
    }

    /**
     * Étape du scénario match test manuel (France–Allemagne, etc.).
     * Paramètres : match_id, step, buteur_id, minute, now, dry_run, score_home, score_away.
     */
    #[Route('/test-match-step', name: 'cron_test_match_step', methods: ['GET', 'POST'])]
    public function testMatchStep(Request $request): JsonResponse
    {
        $denied = $this->denyUnlessValidToken($request);
        if (null !== $denied) {
            return $denied;
        }

        $matchId = (int) $request->query->get('match_id', 0);
        $step = (string) $request->query->get('step', '');
        if ($matchId <= 0 || '' === $step) {
            return $this->json(
                ['error' => 'Paramètres match_id et step requis.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $params = [
            'match_id' => $matchId,
            'step' => $step,
            'dry_run' => filter_var($request->query->get('dry_run', false), \FILTER_VALIDATE_BOOL),
            'notify' => !filter_var($request->query->get('no_notify', false), \FILTER_VALIDATE_BOOL),
        ];

        $buteurId = $request->query->get('buteur_id');
        if (is_numeric($buteurId)) {
            $params['buteur_id'] = (int) $buteurId;
        }

        $minute = $request->query->get('minute');
        if (is_numeric($minute)) {
            $params['minute'] = (int) $minute;
        }

        foreach (['score_home', 'score_away'] as $key) {
            $value = $request->query->get($key);
            if (is_numeric($value)) {
                $params[$key] = (int) $value;
            }
        }

        $now = $request->query->get('now');
        if (\is_string($now) && '' !== $now) {
            $params['now'] = $now;
        }

        try {
            return $this->json($this->testMatchScenarioRunner->run($params));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function denyUnlessValidToken(Request $request): ?JsonResponse
    {
        $expected = null !== $this->cronSecret ? trim($this->cronSecret) : '';
        if ('' === $expected) {
            return $this->json(
                ['error' => 'CRON_SECRET non configuré sur le serveur.'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }

        $provided = (string) $request->query->get('token', '');
        if ('' === $provided) {
            $provided = (string) $request->headers->get('X-Cron-Token', '');
        }

        if (!hash_equals($expected, $provided)) {
            return $this->json(['error' => 'Token invalide.'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
