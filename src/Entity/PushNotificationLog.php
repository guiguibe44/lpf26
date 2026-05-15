<?php

namespace App\Entity;

use App\Repository\PushNotificationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PushNotificationLogRepository::class)]
#[ORM\Table(name: 'push_notification_log')]
class PushNotificationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $url = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $sentBy = null;

    #[ORM\Column]
    private int $targetCount = 0;

    #[ORM\Column]
    private int $sentCount = 0;

    #[ORM\Column]
    private int $failedCount = 0;

    #[ORM\Column]
    private int $removedCount = 0;

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

    public function getSentBy(): ?User
    {
        return $this->sentBy;
    }

    public function setSentBy(?User $sentBy): static
    {
        $this->sentBy = $sentBy;

        return $this;
    }

    public function getTargetCount(): int
    {
        return $this->targetCount;
    }

    public function setTargetCount(int $targetCount): static
    {
        $this->targetCount = $targetCount;

        return $this;
    }

    public function getSentCount(): int
    {
        return $this->sentCount;
    }

    public function setSentCount(int $sentCount): static
    {
        $this->sentCount = $sentCount;

        return $this;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function setFailedCount(int $failedCount): static
    {
        $this->failedCount = $failedCount;

        return $this;
    }

    public function getRemovedCount(): int
    {
        return $this->removedCount;
    }

    public function setRemovedCount(int $removedCount): static
    {
        $this->removedCount = $removedCount;

        return $this;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }
}
