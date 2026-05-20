<?php

declare(strict_types=1);

namespace App\Dto;

final class MatchLiveTeamRow
{
    /**
     * @param list<SimulatedPronosticLine>     $pronostics
     * @param list<array<string, mixed>>     $buteurs
     * @param array{name: string, image: ?string, code: string}|null $activeJoker
     * @param list<array{
     *     code: string,
     *     name: string,
     *     image: ?string,
     *     kind: string,
     *     label: string,
     *     description: ?string,
     *     technical_lines: list<string>
     * }> $jokerBadges
     */
    public function __construct(
        public readonly int $teamId,
        public readonly string $teamName,
        public readonly ?string $teamLogo,
        public readonly int $rankingPosition,
        public readonly int $pronosticMatchPoints,
        public readonly int $buteurMatchPoints,
        public readonly float $simulatedTotalPoints,
        public readonly int $simulatedRankingPosition,
        public readonly array $pronostics,
        public readonly array $buteurs,
        public readonly ?array $activeJoker = null,
        public readonly array $jokerBadges = [],
    ) {
    }

    public function matchPointsTotal(): int
    {
        return $this->pronosticMatchPoints + $this->buteurMatchPoints;
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
            'pronosticMatchPoints' => $this->pronosticMatchPoints,
            'buteurMatchPoints' => $this->buteurMatchPoints,
            'matchPoints' => $this->matchPointsTotal(),
            'simulatedTotalPoints' => $this->simulatedTotalPoints,
            'simulatedRankingPosition' => $this->simulatedRankingPosition,
            'pronostics' => array_map(static fn (SimulatedPronosticLine $line): array => $line->toArray(), $this->pronostics),
            'buteurs' => $this->buteurs,
            'activeJoker' => $this->activeJoker,
            'jokerBadges' => $this->jokerBadges,
        ];
    }
}
