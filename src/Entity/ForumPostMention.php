<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ForumPostMentionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumPostMentionRepository::class)]
#[ORM\Table(name: 'forum_post_mention')]
#[ORM\UniqueConstraint(name: 'UNIQ_FORUM_POST_MENTION', columns: ['forum_post_id', 'mentioned_user_id'])]
class ForumPostMention
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'mentions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ForumPost $forumPost = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $mentionedUser = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getForumPost(): ?ForumPost
    {
        return $this->forumPost;
    }

    public function setForumPost(ForumPost $forumPost): static
    {
        $this->forumPost = $forumPost;

        return $this;
    }

    public function getMentionedUser(): ?User
    {
        return $this->mentionedUser;
    }

    public function setMentionedUser(User $mentionedUser): static
    {
        $this->mentionedUser = $mentionedUser;

        return $this;
    }
}
