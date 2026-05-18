<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\TeamMemberRepository;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Infos affichées pour le joueur connecté (menu, en-têtes, etc.).
 */
final class ConnectedPlayerContext
{
    public function __construct(
        private readonly Security $security,
        private readonly TeamMemberRepository $teamMemberRepository,
    ) {
    }

    /**
     * @return array{
     *     nickname: string,
     *     avatar: string|null,
     *     initials: string,
     *     email: string
     * }|null
     */
    public function getSidebarProfile(): ?array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $email = (string) $user->getEmail();
        $member = $this->teamMemberRepository->findOneBy(['player' => $user]);
        $nickname = $member?->getNickname();
        if (null === $nickname || '' === trim($nickname)) {
            $nickname = '' !== $email ? explode('@', $email)[0] : 'Joueur';
        }

        return [
            'nickname' => $nickname,
            'avatar' => $user->getAvatar(),
            'initials' => self::initialsFrom($nickname, $email),
            'email' => $email,
        ];
    }

    private static function initialsFrom(string $nickname, string $email): string
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
