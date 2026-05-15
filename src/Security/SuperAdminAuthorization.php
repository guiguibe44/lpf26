<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Super administrateur API (synchro pays / matchs / joueurs / médias).
 * Rôle dérivé de l’e-mail, non stocké en base.
 */
final class SuperAdminAuthorization
{
    public const EMAIL = 'guigui@lotopotofoot.fr';

    public function isSuperAdmin(UserInterface $user): bool
    {
        return $user instanceof User && self::isSuperAdminEmail((string) $user->getEmail());
    }

    public static function isSuperAdminEmail(string $email): bool
    {
        return mb_strtolower(trim($email)) === mb_strtolower(self::EMAIL);
    }
}
