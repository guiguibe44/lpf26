<?php

declare(strict_types=1);

namespace App\Dto;

final class KdoPotentialWinnerRow
{
    public function __construct(
        public readonly int $teamId,
        public readonly string $teamName,
        public readonly ?string $teamLogo,
        public readonly int $exactScoresCount,
        public readonly ?int $rankingPositionBefore,
        public readonly bool $isWinner,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'teamId' => $this->teamId,
            'teamName' => $this->teamName,
            'teamLogo' => $this->teamLogo,
            'exactScoresCount' => $this->exactScoresCount,
            'rankingPositionBefore' => $this->rankingPositionBefore,
            'isWinner' => $this->isWinner,
        ];
    }
}
