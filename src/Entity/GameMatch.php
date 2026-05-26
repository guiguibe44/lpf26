<?php

namespace App\Entity;

use App\Repository\GameMatchRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameMatchRepository::class)]
#[ORM\Table(name: '`match`')]
class GameMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Country $paysDomicile = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Country $paysExterieur = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateHeure = null;

    #[ORM\Column(nullable: true)]
    private ?int $scoreDomicile = null;

    #[ORM\Column(nullable: true)]
    private ?int $scoreExterieur = null;

    #[ORM\Column(length: 30)]
    private string $statut = 'SCHEDULED';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phase = null;

    #[ORM\Column(nullable: true)]
    private ?int $pointsScoreExact = null;

    #[ORM\Column(nullable: true)]
    private ?int $pointsBonResultat = null;

    #[ORM\Column(nullable: true)]
    private ?int $pointsMauvaisResultat = null;

    #[ORM\Column(nullable: true)]
    private ?float $coteMin = null;

    #[ORM\Column(nullable: true)]
    private ?float $coteMoyenne = null;

    #[ORM\Column(nullable: true)]
    private ?float $coteMax = null;

    #[ORM\Column(nullable: true)]
    private ?float $coteDomicile = null;

    #[ORM\Column(nullable: true)]
    private ?float $coteNul = null;

    #[ORM\Column(nullable: true)]
    private ?float $coteExterieur = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true, unique: true)]
    private ?int $apiFootballFixtureId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $venueName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referee = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $pushReminderSentAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isKdoMatch = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $apiFootballSyncEnabled = true;

    #[ORM\Column(nullable: true)]
    private ?int $liveElapsedMinute = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $liveScoresFinalizedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $apiFootballLastSyncedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->pointsScoreExact = 30;
        $this->pointsBonResultat = 10;
        $this->pointsMauvaisResultat = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPaysDomicile(): ?Country
    {
        return $this->paysDomicile;
    }

    public function setPaysDomicile(?Country $paysDomicile): static
    {
        $this->paysDomicile = $paysDomicile;

        return $this;
    }

    public function getPaysExterieur(): ?Country
    {
        return $this->paysExterieur;
    }

    public function setPaysExterieur(?Country $paysExterieur): static
    {
        $this->paysExterieur = $paysExterieur;

        return $this;
    }

    public function getDateHeure(): ?\DateTimeImmutable
    {
        return $this->dateHeure;
    }

    public function setDateHeure(\DateTimeImmutable $dateHeure): static
    {
        $this->dateHeure = $dateHeure;

        return $this;
    }

    public function getScoreDomicile(): ?int
    {
        return $this->scoreDomicile;
    }

    public function setScoreDomicile(?int $scoreDomicile): static
    {
        $this->scoreDomicile = $scoreDomicile;

        return $this;
    }

    public function getScoreExterieur(): ?int
    {
        return $this->scoreExterieur;
    }

    public function setScoreExterieur(?int $scoreExterieur): static
    {
        $this->scoreExterieur = $scoreExterieur;

        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getPhase(): ?string
    {
        return $this->phase;
    }

    /**
     * Lettre A–L si la phase est une poule « Group X » (CDM 2026), sinon null.
     */
    public static function extractGroupStandingLetter(?string $phase): ?string
    {
        if (null === $phase || '' === trim($phase)) {
            return null;
        }

        if (1 === preg_match('/^(?:Group|Groupe)\s+([A-L])\b/iu', trim($phase), $m)) {
            return mb_strtoupper($m[1]);
        }

        if (1 === preg_match('/\b(?:Group|Groupe)\s+([A-L])\b/iu', trim($phase), $m)) {
            return mb_strtoupper($m[1]);
        }

        return null;
    }

    public function getGroupStandingLetter(): ?string
    {
        return self::extractGroupStandingLetter($this->phase);
    }

    /** Match de phase de poules (phase « Group X » ou deux sélections du même groupe). */
    public function isGroupStageMatch(): bool
    {
        if (null !== $this->getGroupStandingLetter()) {
            return true;
        }

        $home = $this->getPaysDomicile();
        $away = $this->getPaysExterieur();
        $homeGroup = $home?->getGroupe();
        $awayGroup = $away?->getGroupe();

        return null !== $homeGroup
            && '' !== $homeGroup
            && null !== $awayGroup
            && $homeGroup === $awayGroup;
    }

    public function setPhase(?string $phase): static
    {
        $this->phase = $phase;

        return $this;
    }

    public function getPointsScoreExact(): ?int
    {
        return $this->pointsScoreExact;
    }

    public function setPointsScoreExact(?int $pointsScoreExact): static
    {
        $this->pointsScoreExact = $pointsScoreExact;

        return $this;
    }

    public function getPointsBonResultat(): ?int
    {
        return $this->pointsBonResultat;
    }

    public function setPointsBonResultat(?int $pointsBonResultat): static
    {
        $this->pointsBonResultat = $pointsBonResultat;

        return $this;
    }

    public function getPointsMauvaisResultat(): ?int
    {
        return $this->pointsMauvaisResultat;
    }

    public function setPointsMauvaisResultat(?int $pointsMauvaisResultat): static
    {
        $this->pointsMauvaisResultat = $pointsMauvaisResultat;

        return $this;
    }

    public function getCoteMin(): ?float
    {
        return $this->coteMin;
    }

    public function setCoteMin(?float $coteMin): static
    {
        $this->coteMin = $coteMin;

        return $this;
    }

    public function getCoteMoyenne(): ?float
    {
        return $this->coteMoyenne;
    }

    public function setCoteMoyenne(?float $coteMoyenne): static
    {
        $this->coteMoyenne = $coteMoyenne;

        return $this;
    }

    public function getCoteMax(): ?float
    {
        return $this->coteMax;
    }

    public function setCoteMax(?float $coteMax): static
    {
        $this->coteMax = $coteMax;

        return $this;
    }

    public function getCoteDomicile(): ?float
    {
        return $this->coteDomicile;
    }

    public function setCoteDomicile(?float $coteDomicile): static
    {
        $this->coteDomicile = $coteDomicile;

        return $this;
    }

    public function getCoteNul(): ?float
    {
        return $this->coteNul;
    }

    public function setCoteNul(?float $coteNul): static
    {
        $this->coteNul = $coteNul;

        return $this;
    }

    public function getCoteExterieur(): ?float
    {
        return $this->coteExterieur;
    }

    public function setCoteExterieur(?float $coteExterieur): static
    {
        $this->coteExterieur = $coteExterieur;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getApiFootballFixtureId(): ?int
    {
        return $this->apiFootballFixtureId;
    }

    public function setApiFootballFixtureId(?int $apiFootballFixtureId): static
    {
        $this->apiFootballFixtureId = $apiFootballFixtureId;

        return $this;
    }

    public function getVenueName(): ?string
    {
        return $this->venueName;
    }

    public function setVenueName(?string $venueName): static
    {
        $this->venueName = $venueName;

        return $this;
    }

    public function getReferee(): ?string
    {
        return $this->referee;
    }

    public function setReferee(?string $referee): static
    {
        $this->referee = $referee;

        return $this;
    }

    public function getPushReminderSentAt(): ?\DateTimeImmutable
    {
        return $this->pushReminderSentAt;
    }

    public function setPushReminderSentAt(?\DateTimeImmutable $pushReminderSentAt): static
    {
        $this->pushReminderSentAt = $pushReminderSentAt;

        return $this;
    }

    public function isKdoMatch(): bool
    {
        return $this->isKdoMatch;
    }

    public function setIsKdoMatch(bool $isKdoMatch): static
    {
        $this->isKdoMatch = $isKdoMatch;

        return $this;
    }

    public function isApiFootballSyncEnabled(): bool
    {
        return $this->apiFootballSyncEnabled;
    }

    public function setApiFootballSyncEnabled(bool $apiFootballSyncEnabled): static
    {
        $this->apiFootballSyncEnabled = $apiFootballSyncEnabled;

        return $this;
    }

    public function getLiveElapsedMinute(): ?int
    {
        return $this->liveElapsedMinute;
    }

    public function setLiveElapsedMinute(?int $liveElapsedMinute): static
    {
        $this->liveElapsedMinute = $liveElapsedMinute;

        return $this;
    }

    public function getLiveScoresFinalizedAt(): ?\DateTimeImmutable
    {
        return $this->liveScoresFinalizedAt;
    }

    public function setLiveScoresFinalizedAt(?\DateTimeImmutable $liveScoresFinalizedAt): static
    {
        $this->liveScoresFinalizedAt = $liveScoresFinalizedAt;

        return $this;
    }

    public function getApiFootballLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->apiFootballLastSyncedAt;
    }

    public function setApiFootballLastSyncedAt(?\DateTimeImmutable $apiFootballLastSyncedAt): static
    {
        $this->apiFootballLastSyncedAt = $apiFootballLastSyncedAt;

        return $this;
    }

    public function __toString(): string
    {
        $domicile = $this->paysDomicile?->getNom() ?? 'Equipe A';
        $exterieur = $this->paysExterieur?->getNom() ?? 'Equipe B';

        return sprintf('%s vs %s', $domicile, $exterieur);
    }
}
