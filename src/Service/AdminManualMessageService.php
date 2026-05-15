<?php

namespace App\Service;

use App\Entity\PushNotificationLog;
use App\Entity\User;
use App\Enum\AdminRecipientScope;
use App\Repository\PushSubscriptionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AdminManualMessageService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
        private readonly WebPushService $webPushService,
        private readonly PronosticReminderMailer $mailer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param list<User> $selectedPlayers
     *
     * @return array{
     *     playersTargeted: int,
     *     pushSent: int,
     *     pushFailed: int,
     *     pushRemoved: int,
     *     emailsSent: int,
     *     emailsFailed: int,
     * }
     */
    public function send(
        bool $sendPush,
        bool $sendEmail,
        AdminRecipientScope $scope,
        array|\Doctrine\Common\Collections\Collection $selectedPlayers,
        string $title,
        string $body,
        ?string $url,
        ?User $sentBy,
    ): array {
        $players = $this->resolvePlayers($scope, $this->normalizeUserList($selectedPlayers));
        $summary = [
            'playersTargeted' => \count($players),
            'pushSent' => 0,
            'pushFailed' => 0,
            'pushRemoved' => 0,
            'emailsSent' => 0,
            'emailsFailed' => 0,
        ];

        if ($sendPush && $this->webPushService->isConfigured()) {
            $userIds = array_values(array_filter(array_map(
                static fn (User $u): ?int => $u->getId(),
                $players,
            )));
            $subscriptions = AdminRecipientScope::All === $scope
                ? $this->pushSubscriptionRepository->findAllForBroadcast()
                : $this->pushSubscriptionRepository->findByUserIds($userIds);

            if ([] !== $subscriptions) {
                $pushResult = $this->webPushService->sendToSubscriptions(
                    $subscriptions,
                    $title,
                    $body,
                    $url,
                );
                $summary['pushSent'] = $pushResult['sent'];
                $summary['pushFailed'] = $pushResult['failed'];
                $summary['pushRemoved'] = $pushResult['removed'];
            }
        }

        if ($sendEmail) {
            foreach ($players as $player) {
                try {
                    $this->mailer->send($player, $title, $body, $url ?? '/accueil');
                    ++$summary['emailsSent'];
                } catch (\Throwable) {
                    ++$summary['emailsFailed'];
                }
            }
        }

        $log = (new PushNotificationLog())
            ->setTitle($title)
            ->setBody($body)
            ->setUrl($url)
            ->setSentBy($sentBy)
            ->setSendPush($sendPush)
            ->setSendEmail($sendEmail)
            ->setRecipientScope($scope)
            ->setPlayersTargeted($summary['playersTargeted'])
            ->setTargetCount($summary['playersTargeted'])
            ->setSentCount($summary['pushSent'])
            ->setFailedCount($summary['pushFailed'])
            ->setRemovedCount($summary['pushRemoved'])
            ->setEmailsSentCount($summary['emailsSent'])
            ->setEmailsFailedCount($summary['emailsFailed']);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $summary;
    }

    /**
     * @return list<User>
     */
    private function normalizeUserList(mixed $players): array
    {
        if ($players instanceof \Doctrine\Common\Collections\Collection) {
            return array_values($players->toArray());
        }

        if (\is_array($players)) {
            return array_values($players);
        }

        return [];
    }

    /**
     * @param list<User> $selectedPlayers
     *
     * @return list<User>
     */
    private function resolvePlayers(AdminRecipientScope $scope, array $selectedPlayers): array
    {
        if (AdminRecipientScope::Selection === $scope) {
            return $selectedPlayers;
        }

        return $this->userRepository->findActivePlayersOrderedByEmail();
    }
}
