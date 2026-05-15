<?php

namespace App\Service;

use App\Entity\PushSubscription;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

final class WebPushService
{
    public function __construct(
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $vapidPublicKey,
        private readonly string $vapidPrivateKey,
        private readonly string $vapidSubject,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== $this->vapidPublicKey && '' !== $this->vapidPrivateKey && '' !== $this->vapidSubject;
    }

    public function getVapidPublicKey(): string
    {
        return $this->vapidPublicKey;
    }

    /**
     * @return array{sent: int, failed: int, removed: int}
     */
    public function sendBroadcast(string $title, string $body, ?string $url = null): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Les clés VAPID ne sont pas configurées (VAPID_PUBLIC_KEY, VAPID_PRIVATE_KEY, VAPID_SUBJECT).');
        }

        $subscriptions = $this->pushSubscriptionRepository->findAllForBroadcast();
        if ([] === $subscriptions) {
            return ['sent' => 0, 'failed' => 0, 'removed' => 0];
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ], \JSON_THROW_ON_ERROR);

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $this->vapidSubject,
                'publicKey' => $this->vapidPublicKey,
                'privateKey' => $this->vapidPrivateKey,
            ],
        ]);

        foreach ($subscriptions as $entity) {
            $webPush->queueNotification(
                $this->toSubscription($entity),
                $payload,
            );
        }

        $sent = 0;
        $failed = 0;
        $removed = 0;

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                ++$sent;
                continue;
            }

            ++$failed;
            $this->logger?->warning('Push non délivré', [
                'reason' => $report->getReason(),
                'endpoint' => $report->getRequest()?->getUri()?->__toString(),
                'status' => $report->getResponse()?->getStatusCode(),
            ]);
            $response = $report->getResponse();
            if (null !== $response && \in_array($response->getStatusCode(), [404, 410], true)) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                $stale = $this->pushSubscriptionRepository->findOneByEndpoint($endpoint);
                if (null !== $stale) {
                    $this->entityManager->remove($stale);
                    ++$removed;
                }
            }
        }

        if ($removed > 0) {
            $this->entityManager->flush();
        }

        return ['sent' => $sent, 'failed' => $failed, 'removed' => $removed];
    }

    private function toSubscription(PushSubscription $entity): Subscription
    {
        return Subscription::create([
            'endpoint' => $entity->getEndpoint(),
            'keys' => [
                'p256dh' => $entity->getPublicKey(),
                'auth' => $entity->getAuthToken(),
            ],
            'contentEncoding' => $this->resolveContentEncoding($entity->getContentEncoding()),
        ]);
    }

    private function resolveContentEncoding(?string $encoding): string
    {
        $encoding = null !== $encoding ? trim($encoding) : '';

        if ('' === $encoding) {
            return ContentEncoding::aes128gcm->value;
        }

        try {
            return ContentEncoding::from($encoding)->value;
        } catch (\ValueError) {
            return ContentEncoding::aes128gcm->value;
        }
    }
}
