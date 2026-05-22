<?php

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\MatchReminderLog;
use App\Entity\User;
use App\Enum\NotificationPreference;
use App\Enum\ReminderChannel;
use App\Enum\ReminderDeliveryMode;
use App\Enum\ReminderTrigger;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Envoie une relance à un joueur (push ou e-mail) et journalise le résultat.
 */
final class PlayerReminderDispatcher
{
    public function __construct(
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
        private readonly WebPushService $webPushService,
        private readonly PronosticReminderMailer $pronosticReminderMailer,
        private readonly UserNotificationPreferenceService $preferenceService,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array{channel: ?ReminderChannel, success: bool, skipped: bool, error: ?string, pushSent: int, pushFailed: int}
     */
    public function dispatchToUser(
        User $user,
        string $title,
        string $body,
        ?string $url,
        ReminderDeliveryMode $mode,
        ReminderTrigger $trigger,
        ?GameMatch $match = null,
        ?User $sentBy = null,
    ): array {
        $userId = $user->getId();
        if (null === $userId) {
            return [
                'channel' => ReminderChannel::Email,
                'success' => false,
                'error' => 'Utilisateur sans identifiant.',
                'pushSent' => 0,
                'pushFailed' => 0,
            ];
        }

        $channel = $this->resolveChannel($user, $mode);
        if (null === $channel) {
            return [
                'channel' => null,
                'success' => true,
                'skipped' => true,
                'error' => null,
                'pushSent' => 0,
                'pushFailed' => 0,
            ];
        }

        $result = [
            'channel' => $channel,
            'success' => false,
            'skipped' => false,
            'error' => null,
            'pushSent' => 0,
            'pushFailed' => 0,
        ];

        try {
            if (ReminderChannel::Push === $channel) {
                $subscriptions = $this->pushSubscriptionRepository->findByUserIds([$userId]);
                if ([] === $subscriptions) {
                    throw new \RuntimeException('Aucun abonnement push actif pour ce joueur.');
                }
                if (!$this->webPushService->isConfigured()) {
                    throw new \RuntimeException('Notifications push non configurées sur le serveur.');
                }
                $pushResult = $this->webPushService->sendToSubscriptions($subscriptions, $title, $body, $url);
                $result['pushSent'] = $pushResult['sent'];
                $result['pushFailed'] = $pushResult['failed'];
                $result['success'] = $pushResult['sent'] > 0;
                if (!$result['success']) {
                    $result['error'] = sprintf('%d échec(s) push.', $pushResult['failed']);
                }
            } else {
                $this->pronosticReminderMailer->send($user, $title, $body, $url);
                $result['success'] = true;
            }
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            $this->logger?->warning('Relance joueur échouée', [
                'user' => $user->getEmail(),
                'channel' => $channel->value,
                'error' => $e->getMessage(),
            ]);
        }

        $log = (new MatchReminderLog())
            ->setMatch($match)
            ->setUser($user)
            ->setChannel($channel)
            ->setTrigger($trigger)
            ->setTitle($title)
            ->setBody($body)
            ->setUrl($url)
            ->setSuccess($result['success'])
            ->setErrorMessage($result['error'])
            ->setSentBy($sentBy);

        $this->entityManager->persist($log);

        return $result;
    }

    public function peekDeliveryChannel(User $user, ReminderDeliveryMode $mode): ?ReminderChannel
    {
        return $this->resolveChannel($user, $mode);
    }

    private function resolveChannel(User $user, ReminderDeliveryMode $mode): ?ReminderChannel
    {
        $userId = (int) $user->getId();
        $hasPushSubscription = [] !== $this->pushSubscriptionRepository->findByUserIds([$userId]);
        $pushAllowed = $this->preferenceService->isEnabled($user, NotificationPreference::PronosticReminderPush)
            && $hasPushSubscription
            && $this->webPushService->isConfigured();
        $emailAllowed = $this->preferenceService->isEnabled($user, NotificationPreference::PronosticReminderEmail);

        return match ($mode) {
            ReminderDeliveryMode::PushOnly => $pushAllowed ? ReminderChannel::Push : null,
            ReminderDeliveryMode::EmailOnly => $emailAllowed ? ReminderChannel::Email : null,
            ReminderDeliveryMode::PreferPush => $pushAllowed
                ? ReminderChannel::Push
                : ($emailAllowed ? ReminderChannel::Email : null),
        };
    }
}
