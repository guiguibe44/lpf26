<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserNotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED')]
#[Route('/api/notifications')]
final class NotificationApiController extends AbstractController
{
    #[Route('/unread-count', name: 'api_notifications_unread_count', methods: ['GET'])]
    public function unreadCount(UserNotificationRepository $repository): JsonResponse
    {
        $user = $this->requireUser();

        return $this->json([
            'count' => $repository->countUnreadForUser($user),
        ]);
    }

    #[Route('', name: 'api_notifications_list', methods: ['GET'])]
    public function list(UserNotificationRepository $repository): JsonResponse
    {
        $user = $this->requireUser();
        $items = [];

        foreach ($repository->findRecentForUser($user, 40) as $notification) {
            $items[] = [
                'id' => $notification->getId(),
                'title' => $notification->getTitle(),
                'body' => $notification->getBody(),
                'url' => $notification->getUrl(),
                'read' => $notification->isRead(),
                'createdAt' => $notification->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'type' => $notification->getType()->value,
            ];
        }

        return $this->json(['notifications' => $items]);
    }

    #[Route('/read', name: 'api_notifications_mark_read', methods: ['POST'])]
    public function markRead(Request $request, UserNotificationRepository $repository): JsonResponse
    {
        $user = $this->requireUser();
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => 'JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $markAll = (bool) ($data['all'] ?? false);
        if ($markAll) {
            $repository->markAllReadForUser($user);
        } else {
            $ids = array_values(array_filter(array_map('intval', $data['ids'] ?? [])));
            $repository->markReadByIdsForUser($user, $ids);
        }

        return $this->json([
            'count' => $repository->countUnreadForUser($user),
        ]);
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
