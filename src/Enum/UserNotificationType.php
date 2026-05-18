<?php

declare(strict_types=1);

namespace App\Enum;

enum UserNotificationType: string
{
    case ForumMention = 'forum_mention';

    public function label(): string
    {
        return match ($this) {
            self::ForumMention => 'Mention sur le forum',
        };
    }
}
