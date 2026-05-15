<?php

namespace App\Controller;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use App\Service\WebPushService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/push')]
final class PushNotificationController extends AbstractController
{
    #[Route('/vapid-public-key', name: 'api_push_vapid_public_key', methods: ['GET'])]
    public function vapidPublicKey(WebPushService $webPushService): JsonResponse
    {
        if (!$webPushService->isConfigured()) {
            return $this->json(['configured' => false, 'publicKey' => null]);
        }

        return $this->json([
            'configured' => true,
            'publicKey' => $webPushService->getVapidPublicKey(),
        ]);
    }

    #[Route('/subscribe', name: 'api_push_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        EntityManagerInterface $entityManager,
        PushSubscriptionRepository $pushSubscriptionRepository,
        WebPushService $webPushService,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'Corps JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $csrfToken = (string) ($request->headers->get('X-CSRF-Token') ?? $data['_csrf_token'] ?? '');
        unset($data['_csrf_token']);
        if (!$this->isCsrfTokenValid('push_subscribe', $csrfToken)) {
            return $this->json(['error' => 'Jeton CSRF invalide. Rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        if (!$webPushService->isConfigured()) {
            return $this->json(['error' => 'Notifications push non configurées sur le serveur.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $endpoint = isset($data['endpoint']) ? (string) $data['endpoint'] : '';
        $keys = $data['keys'] ?? null;
        if ('' === $endpoint || !\is_array($keys)) {
            return $this->json(['error' => 'Abonnement incomplet.'], Response::HTTP_BAD_REQUEST);
        }

        $p256dh = isset($keys['p256dh']) ? (string) $keys['p256dh'] : '';
        $auth = isset($keys['auth']) ? (string) $keys['auth'] : '';
        if ('' === $p256dh || '' === $auth) {
            return $this->json(['error' => 'Clés d\'abonnement manquantes.'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $pushSubscriptionRepository->findOneByEndpoint($endpoint);
        if (null === $existing) {
            $existing = new PushSubscription();
            $existing->setEndpoint($endpoint);
            $entityManager->persist($existing);
        }

        $contentEncoding = isset($data['contentEncoding']) ? trim((string) $data['contentEncoding']) : '';
        if ('' === $contentEncoding) {
            $contentEncoding = 'aes128gcm';
        }

        $existing
            ->setUser($user)
            ->setPublicKey($p256dh)
            ->setAuthToken($auth)
            ->setContentEncoding($contentEncoding)
            ->setUserAgent(substr((string) $request->headers->get('User-Agent', ''), 0, 512));

        $entityManager->flush();

        return $this->json(['ok' => true]);
    }

    #[Route('/unsubscribe', name: 'api_push_unsubscribe', methods: ['POST'])]
    public function unsubscribe(
        Request $request,
        EntityManagerInterface $entityManager,
        PushSubscriptionRepository $pushSubscriptionRepository,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            $data = [];
        }

        $csrfToken = (string) ($request->headers->get('X-CSRF-Token') ?? $data['_csrf_token'] ?? '');
        if (!$this->isCsrfTokenValid('push_subscribe', $csrfToken)) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $endpoint = isset($data['endpoint']) ? (string) $data['endpoint'] : '';

        if ('' !== $endpoint) {
            $subscription = $pushSubscriptionRepository->findOneByEndpoint($endpoint);
            if (null !== $subscription && $subscription->getUser()?->getId() === $user->getId()) {
                $entityManager->remove($subscription);
                $entityManager->flush();
            }
        } else {
            $pushSubscriptionRepository->deleteByUser($user);
        }

        return $this->json(['ok' => true]);
    }
}
