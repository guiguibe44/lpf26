<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\But;
use App\Entity\Buteur;
use App\Entity\GameMatch;
use App\Entity\User;
use App\Enum\NotificationPreference;
use App\Repository\PushSubscriptionRepository;
use App\Repository\UserRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Alerte les joueurs dont le buteur choisi vient de marquer.
 */
final class ButeurGoalNotificationService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserNotificationPreferenceService $preferenceService,
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
        private readonly WebPushService $webPushService,
        private readonly PronosticReminderMailer $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function notifyForNewBut(But $but): void
    {
        $buteur = $but->getButeur();
        if (!$buteur instanceof Buteur) {
            return;
        }

        $buteurId = $buteur->getId();
        if (null === $buteurId) {
            return;
        }

        $users = $this->userRepository->findBy(['buteurChoisi' => $buteur]);
        if ([] === $users) {
            return;
        }

        [$title, $body, $url] = $this->buildMessage($but, $buteur);

        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $this->notifyUser($user, $title, $body, $url);
        }
    }

    private function notifyUser(User $user, string $title, string $body, string $url): void
    {
        $userId = $user->getId();
        if (null === $userId) {
            return;
        }

        if ($this->preferenceService->isEnabled($user, NotificationPreference::ButeurGoalPush)
            && $this->webPushService->isConfigured()
        ) {
            $subscriptions = $this->pushSubscriptionRepository->findByUserIds([$userId]);
            if ([] !== $subscriptions) {
                $this->webPushService->sendToSubscriptions($subscriptions, $title, $body, $url);
            }
        }

        if ($this->preferenceService->isEnabled($user, NotificationPreference::ButeurGoalEmail)) {
            try {
                $this->mailer->send($user, $title, $body, $url, 'Voir mon buteur');
            } catch (\Throwable) {
                // Ne pas bloquer la synchro des buts.
            }
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function buildMessage(But $but, Buteur $buteur): array
    {
        $match = $but->getMatchRef();
        $matchLabel = 'un match';
        if ($match instanceof GameMatch) {
            $home = $match->getPaysDomicile()?->getNom() ?? '?';
            $away = $match->getPaysExterieur()?->getNom() ?? '?';
            $matchLabel = sprintf('%s — %s', $home, $away);
        }

        $minute = $but->getMinute();
        $minuteSuffix = null !== $minute && $minute > 0 ? sprintf(' (%d′)', $minute) : '';
        $points = $but->getPointsAttribues();
        $playerName = trim(sprintf('%s %s', (string) $buteur->getPrenom(), (string) $buteur->getNom()));

        $title = sprintf('But pour %s !', $playerName);
        $body = sprintf(
            '%s a marqué%s lors de %s. Points buteur enregistrés : %d.',
            $playerName,
            $minuteSuffix,
            $matchLabel,
            $points,
        );

        return [$title, $body, $this->urlGenerator->generate('app_account', ['_fragment' => 'buteur'], UrlGeneratorInterface::ABSOLUTE_PATH)];
    }
}
