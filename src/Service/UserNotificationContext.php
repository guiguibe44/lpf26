<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserNotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;

final class UserNotificationContext
{
    public function __construct(
        private readonly Security $security,
        private readonly UserNotificationRepository $notificationRepository,
    ) {
    }

    public function getUnreadCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 0;
        }

        return $this->notificationRepository->countUnreadForUser($user);
    }
}
