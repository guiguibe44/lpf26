<?php

declare(strict_types=1);

namespace App\Dto;

final class SimulatedPronosticLine
{
    public function __construct(
        public readonly int $pronosticId,
        public readonly int $teamId,
        public readonly string $playerLabel,
        public readonly int $predHome,
        public readonly int $predAway,
        public readonly int $basePoints,
        public readonly float $coefficient,
        public readonly float $points,
        public readonly bool $priseRisque,
        public readonly float $teamPoints,
        public readonly bool $scoreInverted = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'pronosticId' => $this->pronosticId,
            'teamId' => $this->teamId,
            'playerLabel' => $this->playerLabel,
            'predHome' => $this->predHome,
            'predAway' => $this->predAway,
            'basePoints' => $this->basePoints,
            'coefficient' => $this->coefficient,
            'points' => $this->points,
            'teamPoints' => $this->teamPoints,
            'priseRisque' => $this->priseRisque,
            'scoreInverted' => $this->scoreInverted,
        ];
    }
}
