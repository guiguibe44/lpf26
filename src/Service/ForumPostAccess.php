<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ForumPost;
use App\Entity\User;

final class ForumPostAccess
{
    public function canModify(ForumPost $post, User $user): bool
    {
        $author = $post->getAuthor();
        if (!$author instanceof User || $author->getId() !== $user->getId()) {
            return false;
        }

        return !$post->hasReplies();
    }
}
