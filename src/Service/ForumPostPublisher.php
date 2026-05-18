<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ForumPost;
use App\Entity\ForumPostMention;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persiste un message forum, enregistre les mentions et déclenche les notifications.
 */
final class ForumPostPublisher
{
    public function __construct(
        private readonly ForumContentSanitizer $sanitizer,
        private readonly ForumMentionParser $mentionParser,
        private readonly ForumNotificationService $notificationService,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function publish(ForumPost $post, string $rawHtml, User $author): void
    {
        $content = $this->sanitizer->sanitize($rawHtml);
        if ($this->sanitizer->isEffectivelyEmpty($content)) {
            throw new \InvalidArgumentException('Le message ne peut pas être vide.');
        }

        $post->setContent($content);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $this->syncMentions($post, $content, $author);
    }

    public function update(ForumPost $post, string $rawHtml, User $author): void
    {
        $content = $this->sanitizer->sanitize($rawHtml);
        if ($this->sanitizer->isEffectivelyEmpty($content)) {
            throw new \InvalidArgumentException('Le message ne peut pas être vide.');
        }

        $post->setContent($content);
        $post->touchUpdatedAt();
        $this->entityManager->flush();

        $this->syncMentions($post, $content, $author);
    }

    public function delete(ForumPost $post): void
    {
        $this->entityManager->remove($post);
        $this->entityManager->flush();
    }

    private function syncMentions(ForumPost $post, string $content, User $author): void
    {
        foreach ($post->getMentions()->toArray() as $mention) {
            $post->getMentions()->removeElement($mention);
            $this->entityManager->remove($mention);
        }
        $this->entityManager->flush();

        $mentionedIds = $this->mentionParser->extractMentionedUserIds($content);
        $authorId = (int) $author->getId();
        $notified = [];

        foreach ($mentionedIds as $userId) {
            if ($userId === $authorId) {
                continue;
            }
            $mentioned = $this->userRepository->find($userId);
            if (!$mentioned instanceof User) {
                continue;
            }

            $mention = (new ForumPostMention())
                ->setForumPost($post)
                ->setMentionedUser($mentioned);
            $this->entityManager->persist($mention);
            $notified[] = $mentioned;
        }

        $this->entityManager->flush();

        $this->notificationService->notifyForumMentions($post, $author, $notified);
    }
}
