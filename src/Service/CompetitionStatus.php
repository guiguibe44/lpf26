<?php

namespace App\Service;

final readonly class CompetitionStatus
{
    public function __construct(
        private ?string $competitionStartAt,
    ) {
    }

    public function isStarted(): bool
    {
        $startAt = $this->getStartAt();
        if (null === $startAt) {
            return false;
        }

        return new \DateTimeImmutable() >= $startAt;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        if (null === $this->competitionStartAt || '' === trim($this->competitionStartAt)) {
            return null;
        }

        try {
            return new \DateTimeImmutable($this->competitionStartAt);
        } catch (\Throwable) {
            return null;
        }
    }
}
