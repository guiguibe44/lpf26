<?php

declare(strict_types=1);

namespace App\Dto;

final class KdoMatchOutlook
{
    /**
     * @param list<KdoPotentialWinnerRow> $potentialWinners
     */
    public function __construct(
        public readonly int $scoreDomicile,
        public readonly int $scoreExterieur,
        public readonly int $maxExactScores,
        public readonly ?int $winnerTeamId,
        public readonly array $potentialWinners,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'scoreDomicile' => $this->scoreDomicile,
            'scoreExterieur' => $this->scoreExterieur,
            'maxExactScores' => $this->maxExactScores,
            'winnerTeamId' => $this->winnerTeamId,
            'potentialWinners' => array_map(
                static fn (KdoPotentialWinnerRow $row): array => $row->toArray(),
                $this->potentialWinners,
            ),
        ];
    }
}
