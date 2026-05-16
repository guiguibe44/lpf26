<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeamJokerUsageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamJokerUsageRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_TEAM_JOKER', fields: ['team', 'joker'])]
#[ORM\UniqueConstraint(name: 'UNIQ_TEAM_MATCH_JOKER', fields: ['team', 'match'])]
class TeamJokerUsage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Joker $joker = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?GameMatch $match = null;

    #[ORM\Column]
    private \DateTimeImmutable $placedAt;

    public function __construct()
    {
        $this->placedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getJoker(): ?Joker
    {
        return $this->joker;
    }

    public function setJoker(?Joker $joker): static
    {
        $this->joker = $joker;

        return $this;
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

    public function getPlacedAt(): \DateTimeImmutable
    {
        return $this->placedAt;
    }

    public function setPlacedAt(\DateTimeImmutable $placedAt): static
    {
        $this->placedAt = $placedAt;

        return $this;
    }
}
