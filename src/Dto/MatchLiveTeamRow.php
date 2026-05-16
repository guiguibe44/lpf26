<?php

declare(strict_types=1);

namespace App\Dto;

final class MatchLiveTeamRow
{
    /**
     * @param list<SimulatedPronosticLine>     $pronostics
     * @param list<array<string, mixed>>     $buteurs
     */
    public function __construct(
        public readonly int $teamId,
        public readonly string $teamName,
        public readonly ?string $teamLogo,
        public readonly int $rankingPosition,
        public readonly int $matchPoints,
        public readonly float $simulatedTotalPoints,
        public readonly int $simulatedRankingPosition,
        public readonly array $pronostics,
        public readonly array $buteurs,
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
            'rankingPosition' => $this->rankingPosition,
            'matchPoints' => $this->matchPoints,
            'simulatedTotalPoints' => $this->simulatedTotalPoints,
            'simulatedRankingPosition' => $this->simulatedRankingPosition,
            'pronostics' => array_map(static fn (SimulatedPronosticLine $line): array => $line->toArray(), $this->pronostics),
            'buteurs' => $this->buteurs,
        ];
    }
}
