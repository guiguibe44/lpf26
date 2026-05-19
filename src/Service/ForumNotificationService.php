<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ForumPost;
use App\Entity\User;
use App\Entity\UserNotification;
use App\Enum\NotificationPreference;
use App\Enum\UserNotificationType;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ForumNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ForumAuthorResolver $authorResolver,
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
        private readonly WebPushService $webPushService,
        private readonly UserNotificationPreferenceService $preferenceService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param list<User> $mentionedUsers
     */
    public function notifyForumMentions(ForumPost $post, User $actor, array $mentionedUsers): void
    {
        if ([] === $mentionedUsers) {
            return;
        }

        $actorName = $this->authorResolver->getDisplayName($actor);
        $postId = $post->getId();
        $root = $post->isRoot() ? $post : $post->getParent();
        $anchorId = $root?->getId() ?? $postId;
        $path = $this->urlGenerator->generate('app_forum', [
            '_fragment' => 'forum-post-'.($anchorId ?? $postId),
        ]);
        $excerpt = $this->buildExcerpt((string) $post->getContent());

        foreach ($mentionedUsers as $recipient) {
            $notification = (new UserNotification())
                ->setRecipient($recipient)
                ->setActor($actor)
                ->setForumPost($post)
                ->setType(UserNotificationType::ForumMention)
                ->setTitle(sprintf('%s vous a mentionné', $actorName))
                ->setBody($excerpt)
                ->setUrl($path);

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            $this->sendPushIfSubscribed($recipient, $notification->getTitle(), $notification->getBody(), $path);
        }
    }

    private function sendPushIfSubscribed(User $user, string $title, string $body, string $url): void
    {
        if (!$this->preferenceService->isEnabled($user, NotificationPreference::ForumMentionPush)) {
            return;
        }

        $userId = $user->getId();
        if (null === $userId || !$this->webPushService->isConfigured()) {
            return;
        }

        $subscriptions = $this->pushSubscriptionRepository->findByUserIds([$userId]);
        if ([] === $subscriptions) {
            return;
        }

        $this->webPushService->sendToSubscriptions($subscriptions, $title, $body, $url);
    }

    private function buildExcerpt(string $html, int $maxLength = 140): string
    {
        $text = trim(html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return mb_substr($text, 0, $maxLength - 1).'…';
    }
}
