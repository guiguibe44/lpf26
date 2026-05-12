<?php

namespace App\Entity;

use App\Repository\PronosticRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PronosticRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_PRONOSTIC_USER_MATCH', fields: ['joueur', 'match'])]
class Pronostic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $joueur = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameMatch $match = null;

    #[ORM\Column]
    private ?int $scoreDomicile = null;

    #[ORM\Column]
    private ?int $scoreExterieur = null;

    #[ORM\Column(nullable: true)]
    private ?float $points = null;

    #[ORM\Column(nullable: true)]
    private ?int $pointsBase = null;

    #[ORM\Column(nullable: true)]
    private ?float $coteCoefficient = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $priseRisque = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJoueur(): ?User
    {
        return $this->joueur;
    }

    public function setJoueur(?User $joueur): static
    {
        $this->joueur = $joueur;

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

    public function getScoreDomicile(): ?int
    {
        return $this->scoreDomicile;
    }

    public function setScoreDomicile(int $scoreDomicile): static
    {
        $this->scoreDomicile = $scoreDomicile;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getScoreExterieur(): ?int
    {
        return $this->scoreExterieur;
    }

    public function setScoreExterieur(int $scoreExterieur): static
    {
        $this->scoreExterieur = $scoreExterieur;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getPoints(): ?float
    {
        return $this->points;
    }

    public function setPoints(?float $points): static
    {
        $this->points = $points;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getPointsBase(): ?int
    {
        return $this->pointsBase;
    }

    public function setPointsBase(?int $pointsBase): static
    {
        $this->pointsBase = $pointsBase;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCoteCoefficient(): ?float
    {
        return $this->coteCoefficient;
    }

    public function setCoteCoefficient(?float $coteCoefficient): static
    {
        $this->coteCoefficient = $coteCoefficient;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isPriseRisque(): bool
    {
        return $this->priseRisque;
    }

    public function setPriseRisque(bool $priseRisque): static
    {
        $this->priseRisque = $priseRisque;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
