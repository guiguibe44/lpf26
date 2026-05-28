<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Progression de la synchro joueurs « tous les pays » par lots (fichier var/).
 */
final class ApiFootballPlayerBatchSyncState
{
    private const STATE_FILE = 'api_football_player_batch_sync.json';

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function reset(): void
    {
        $path = $this->getFilePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @return array{
     *     league_id: int,
     *     season: int,
     *     teams: list<array{id: int, name: string}>,
     *     next_index: int,
     *     completed: bool,
     *     totals: array{created: int, updated: int, skipped: int}
     * }|null
     */
    public function load(): ?array
    {
        $path = $this->getFilePath();
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if (false === $raw || '' === $raw) {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($data)) {
            return null;
        }

        return $this->normalizeState($data);
    }

    /**
     * @param list<array{id: int, name: string}> $teams
     * @param array{created: int, updated: int, skipped: int} $totals
     */
    public function save(
        int $leagueId,
        int $season,
        array $teams,
        int $nextIndex,
        bool $completed,
        array $totals,
    ): void {
        $varDir = $this->projectDir.'/var';
        if (!is_dir($varDir)) {
            mkdir($varDir, 0775, true);
        }

        $payload = [
            'league_id' => $leagueId,
            'season' => $season,
            'teams' => $teams,
            'next_index' => $nextIndex,
            'completed' => $completed,
            'totals' => [
                'created' => max(0, (int) ($totals['created'] ?? 0)),
                'updated' => max(0, (int) ($totals['updated'] ?? 0)),
                'skipped' => max(0, (int) ($totals['skipped'] ?? 0)),
            ],
            'updated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        file_put_contents(
            $this->getFilePath(),
            json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    private function getFilePath(): string
    {
        return $this->projectDir.'/var/'.self::STATE_FILE;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     league_id: int,
     *     season: int,
     *     teams: list<array{id: int, name: string}>,
     *     next_index: int,
     *     completed: bool,
     *     totals: array{created: int, updated: int, skipped: int}
     * }|null
     */
    private function normalizeState(array $data): ?array
    {
        $teams = [];
        foreach ($data['teams'] ?? [] as $team) {
            if (!\is_array($team)) {
                continue;
            }

            $id = $team['id'] ?? null;
            $name = $team['name'] ?? null;
            if (!is_numeric($id) || !\is_string($name) || '' === trim($name)) {
                continue;
            }

            $teams[] = ['id' => (int) $id, 'name' => trim($name)];
        }

        if ([] === $teams) {
            return null;
        }

        return [
            'league_id' => (int) ($data['league_id'] ?? 0),
            'season' => (int) ($data['season'] ?? 0),
            'teams' => $teams,
            'next_index' => max(0, (int) ($data['next_index'] ?? 0)),
            'completed' => (bool) ($data['completed'] ?? false),
            'totals' => [
                'created' => max(0, (int) ($data['totals']['created'] ?? 0)),
                'updated' => max(0, (int) ($data['totals']['updated'] ?? 0)),
                'skipped' => max(0, (int) ($data['totals']['skipped'] ?? 0)),
            ],
        ];
    }
}
