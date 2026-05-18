<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ForumPost;
use App\Entity\User;
use App\Repository\TeamMemberRepository;

final class ForumAuthorResolver
{
    public function __construct(
        private readonly TeamMemberRepository $teamMemberRepository,
    ) {
    }

    public function getDisplayName(User $user): string
    {
        $member = $this->teamMemberRepository->findOneBy(['player' => $user]);
        $nickname = $member?->getNickname();
        if (null !== $nickname && '' !== trim($nickname)) {
            return trim($nickname);
        }

        $email = (string) $user->getEmail();

        return '' !== $email ? explode('@', $email)[0] : 'Joueur';
    }

    /**
     * @param iterable<ForumPost> $posts
     *
     * @return array<int, array{nickname: string, avatar: string|null, initials: string}>
     */
    public function buildAuthorMap(iterable $posts): array
    {
        $map = [];

        foreach ($posts as $post) {
            $this->collectAuthor($map, $post->getAuthor());
            foreach ($post->getReplies() as $reply) {
                $this->collectAuthor($map, $reply->getAuthor());
            }
        }

        return $map;
    }

    /**
     * @param array<int, array{nickname: string, avatar: string|null, initials: string}> $map
     */
    private function collectAuthor(array &$map, ?User $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        $id = (int) $user->getId();
        if (isset($map[$id])) {
            return;
        }

        $nickname = $this->getDisplayName($user);
        $email = (string) $user->getEmail();

        $map[$id] = [
            'nickname' => $nickname,
            'avatar' => $user->getAvatar(),
            'initials' => $this->initialsFrom($nickname, $email),
        ];
    }

    private function initialsFrom(string $nickname, string $email): string
    {
        $parts = preg_split('/\s+/', trim($nickname)) ?: [];
        if (\count($parts) >= 2) {
            return mb_strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[1], 0, 1));
        }

        if ('' !== trim($nickname)) {
            return mb_strtoupper(mb_substr(trim($nickname), 0, 2));
        }

        return mb_strtoupper(mb_substr($email, 0, 1));
    }
}
