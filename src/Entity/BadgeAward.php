<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BadgeAwardRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BadgeAwardRepository::class)]
#[ORM\Table(name: 'badge_award')]
class BadgeAward
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BadgeDefinition $badgeDefinition = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Team $team = null;

    #[ORM\Column]
    private \DateTimeImmutable $awardedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $seenAt = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->awardedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBadgeDefinition(): ?BadgeDefinition
    {
        return $this->badgeDefinition;
    }

    public function setBadgeDefinition(?BadgeDefinition $badgeDefinition): static
    {
        $this->badgeDefinition = $badgeDefinition;

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

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function getAwardedAt(): \DateTimeImmutable
    {
        return $this->awardedAt;
    }

    public function setAwardedAt(\DateTimeImmutable $awardedAt): static
    {
        $this->awardedAt = $awardedAt;

        return $this;
    }

    public function getSeenAt(): ?\DateTimeImmutable
    {
        return $this->seenAt;
    }

    public function setSeenAt(?\DateTimeImmutable $seenAt): static
    {
        $this->seenAt = $seenAt;

        return $this;
    }

    public function isSeen(): bool
    {
        return null !== $this->seenAt;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function __toString(): string
    {
        $badge = $this->badgeDefinition?->getName() ?? 'Badge';

        if (null !== $this->user) {
            return sprintf('%s — %s', $badge, (string) $this->user->getEmail());
        }

        if (null !== $this->team) {
            return sprintf('%s — %s', $badge, (string) $this->team->getName());
        }

        return $badge;
    }
}
