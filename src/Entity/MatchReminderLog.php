<?php

namespace App\Entity;

use App\Enum\ReminderChannel;
use App\Enum\ReminderTrigger;
use App\Repository\MatchReminderLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MatchReminderLogRepository::class)]
#[ORM\Table(name: 'match_reminder_log')]
#[ORM\Index(columns: ['sent_at'], name: 'IDX_MATCH_REMINDER_SENT_AT')]
class MatchReminderLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?GameMatch $match = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 10, enumType: ReminderChannel::class)]
    private ReminderChannel $channel = ReminderChannel::Email;

    #[ORM\Column(name: 'trigger_type', length: 10, enumType: ReminderTrigger::class)]
    private ReminderTrigger $trigger = ReminderTrigger::Auto;

    #[ORM\Column(length: 120)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $url = null;

    #[ORM\Column]
    private bool $success = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $sentBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $sentAt;

    public function __construct()
    {
        $this->sentAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMatch(): ?GameMatch
    {
        return $this->match;
    }

    public function setMatch(?GameMatch $match): static
    {
        $this->match = $match;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getChannel(): ReminderChannel
    {
        return $this->channel;
    }

    public function setChannel(ReminderChannel $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getTrigger(): ReminderTrigger
    {
        return $this->trigger;
    }

    public function setTrigger(ReminderTrigger $trigger): static
    {
        $this->trigger = $trigger;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): static
    {
        $this->success = $success;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getSentBy(): ?User
    {
        return $this->sentBy;
    }

    public function setSentBy(?User $sentBy): static
    {
        $this->sentBy = $sentBy;

        return $this;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }
}
