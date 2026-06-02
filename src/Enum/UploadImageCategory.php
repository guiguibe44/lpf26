<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Profils de redimensionnement / compression par dossier d’upload.
 */
enum UploadImageCategory: string
{
    case Avatar = 'avatars';
    case EditorialAuthor = 'editorial-authors';
    case TeamLogo = 'team-logos';
    case Joker = 'jokers';
    case Buteur = 'buteurs';
    case Flag = 'drapeaux';

    public static function tryFromSubdir(string $subdir): ?self
    {
        return self::tryFrom($subdir);
    }

    public function maxWidth(): int
    {
        return match ($this) {
            self::Avatar => 512,
            self::EditorialAuthor => 256,
            self::TeamLogo => 640,
            self::Joker => 960,
            self::Buteur => 720,
            self::Flag => 320,
        };
    }

    public function maxHeight(): int
    {
        return match ($this) {
            self::Avatar => 512,
            self::EditorialAuthor => 256,
            self::TeamLogo => 640,
            self::Joker => 960,
            self::Buteur => 720,
            self::Flag => 320,
        };
    }

    /** Ne pas agrandir une image plus petite que la cible. */
    public function allowUpscale(): bool
    {
        return false;
    }
}
