<?php

namespace App\Entity;

use App\Repository\ButRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ButRepository::class)]
class But
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'buts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Buteur $buteur = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameMatch $matchRef = null;

    #[ORM\Column(nullable: true)]
    private ?int $minute = null;

    #[ORM\Column]
    private int $pointsAttribues = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 160, nullable: true, unique: true)]
    private ?string $apiSportsEventKey = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getButeur(): ?Buteur
    {
        return $this->buteur;
    }

    public function setButeur(?Buteur $buteur): static
    {
        $this->buteur = $buteur;

        return $this;
    }

    public function getMatchRef(): ?GameMatch
    {
        return $this->matchRef;
    }

    public function setMatchRef(?GameMatch $matchRef): static
    {
        $this->matchRef = $matchRef;

        return $this;
    }

    public function getMinute(): ?int
    {
        return $this->minute;
    }

    public function setMinute(?int $minute): static
    {
        $this->minute = $minute;

        return $this;
    }

    public function getPointsAttribues(): int
    {
        return $this->pointsAttribues;
    }

    public function setPointsAttribues(int $pointsAttribues): static
    {
        $this->pointsAttribues = $pointsAttribues;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getApiSportsEventKey(): ?string
    {
        return $this->apiSportsEventKey;
    }

    public function setApiSportsEventKey(?string $apiSportsEventKey): static
    {
        $this->apiSportsEventKey = $apiSportsEventKey;

        return $this;
    }

    public function __toString(): string
    {
        $buteur = $this->buteur?->__toString() ?? 'Buteur';
        $match = $this->matchRef?->__toString() ?? 'Match';

        return sprintf('%s - %s', $buteur, $match);
    }
}
