<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ForumPost;
use App\Entity\User;
use App\Service\ForumPostAccess;
use PHPUnit\Framework\TestCase;

final class ForumPostAccessTest extends TestCase
{
    private ForumPostAccess $access;

    protected function setUp(): void
    {
        $this->access = new ForumPostAccess();
    }

    public function testAuthorCanModifyWhenNoReplies(): void
    {
        $user = $this->user(1);
        $post = (new ForumPost())->setAuthor($user);

        self::assertTrue($this->access->canModify($post, $user));
    }

    public function testAuthorCannotModifyWhenPostHasReplies(): void
    {
        $user = $this->user(1);
        $post = (new ForumPost())->setAuthor($user);
        $post->getReplies()->add((new ForumPost())->setAuthor($user)->setParent($post));

        self::assertFalse($this->access->canModify($post, $user));
    }

    public function testOtherUserCannotModify(): void
    {
        $author = $this->user(1);
        $other = $this->user(2);
        $post = (new ForumPost())->setAuthor($author);

        self::assertFalse($this->access->canModify($post, $other));
    }

    public function testAuthorCanModifyOwnReply(): void
    {
        $user = $this->user(1);
        $root = (new ForumPost())->setAuthor($this->user(2));
        $reply = (new ForumPost())->setAuthor($user)->setParent($root);

        self::assertTrue($this->access->canModify($reply, $user));
    }

    private function user(int $id): User
    {
        $user = new User();
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
