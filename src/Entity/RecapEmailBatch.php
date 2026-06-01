<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RecapEmailBatchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RecapEmailBatchRepository::class)]
#[ORM\Table(name: 'recap_email_batch')]
#[ORM\Index(columns: ['sent_at'], name: 'IDX_RECAP_EMAIL_BATCH_SENT_AT')]
class RecapEmailBatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $periodStart;

    #[ORM\Column]
    private \DateTimeImmutable $periodEnd;

    #[ORM\Column]
    private int $emailsSent = 0;

    #[ORM\Column]
    private int $teamsNotified = 0;

    #[ORM\Column]
    private int $matchesInPeriod = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $dryRun = false;

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

    public function getPeriodStart(): \DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function setPeriodStart(\DateTimeImmutable $periodStart): static
    {
        $this->periodStart = $periodStart;

        return $this;
    }

    public function getPeriodEnd(): \DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(\DateTimeImmutable $periodEnd): static
    {
        $this->periodEnd = $periodEnd;

        return $this;
    }

    public function getEmailsSent(): int
    {
        return $this->emailsSent;
    }

    public function setEmailsSent(int $emailsSent): static
    {
        $this->emailsSent = $emailsSent;

        return $this;
    }

    public function getTeamsNotified(): int
    {
        return $this->teamsNotified;
    }

    public function setTeamsNotified(int $teamsNotified): static
    {
        $this->teamsNotified = $teamsNotified;

        return $this;
    }

    public function getMatchesInPeriod(): int
    {
        return $this->matchesInPeriod;
    }

    public function setMatchesInPeriod(int $matchesInPeriod): static
    {
        $this->matchesInPeriod = $matchesInPeriod;

        return $this;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function setDryRun(bool $dryRun): static
    {
        $this->dryRun = $dryRun;

        return $this;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }
}
