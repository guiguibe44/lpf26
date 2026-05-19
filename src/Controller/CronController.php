<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LiveMatchSyncService;
use App\Service\MatchPronosticReminderService;
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
