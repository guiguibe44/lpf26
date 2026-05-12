<?php

namespace App\Entity;

use App\Repository\TeamRankingSnapshotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamRankingSnapshotRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_TEAM_MATCH_SNAPSHOT', fields: ['matchRef', 'team'])]
class TeamRankingSnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?GameMatch $matchRef = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private float $totalPoints = 0.0;

    #[ORM\Column]
    private int $scoresExacts = 0;

    #[ORM\Column]
    private int $bonsResultats = 0;

    #[ORM\Column]
    private int $prisesRisque = 0;

    #[ORM\Column]
    private int $resultatsFaux = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getTotalPoints(): float
    {
        return $this->totalPoints;
    }

    public function setTotalPoints(float $totalPoints): static
    {
        $this->totalPoints = $totalPoints;

        return $this;
    }

    public function getScoresExacts(): int
    {
        return $this->scoresExacts;
    }

    public function setScoresExacts(int $scoresExacts): static
    {
        $this->scoresExacts = $scoresExacts;

        return $this;
    }

    public function getBonsResultats(): int
    {
        return $this->bonsResultats;
    }

    public function setBonsResultats(int $bonsResultats): static
    {
        $this->bonsResultats = $bonsResultats;

        return $this;
    }

    public function getPrisesRisque(): int
    {
        return $this->prisesRisque;
    }

    public function setPrisesRisque(int $prisesRisque): static
    {
        $this->prisesRisque = $prisesRisque;

        return $this;
    }

    public function getResultatsFaux(): int
    {
        return $this->resultatsFaux;
    }

    public function setResultatsFaux(int $resultatsFaux): static
    {
        $this->resultatsFaux = $resultatsFaux;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
