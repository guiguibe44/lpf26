<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Progression de l'enrichissement des profils joueurs (prénom complet via /players?id=) par lots de pays.
 */
final class ApiFootballPlayerProfileEnrichBatchSyncState
{
    private const STATE_FILE = 'api_football_player_profile_enrich_batch.json';

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
     *     season: int,
     *     countries: list<array{id: int, name: string}>,
     *     next_country_index: int,
     *     completed: bool,
     *     totals: array{updated: int, skipped: int, errors: int, api_calls: int}
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
     * @param list<array{id: int, name: string}> $countries
     * @param array{updated: int, skipped: int, errors: int, api_calls: int} $totals
     */
    public function save(
        int $season,
        array $countries,
        int $nextCountryIndex,
        bool $completed,
        array $totals,
    ): void {
        $varDir = $this->projectDir.'/var';
        if (!is_dir($varDir)) {
            mkdir($varDir, 0775, true);
        }

        $payload = [
            'season' => $season,
            'countries' => $countries,
            'next_country_index' => max(0, $nextCountryIndex),
            'completed' => $completed,
            'totals' => [
                'updated' => max(0, (int) ($totals['updated'] ?? 0)),
                'skipped' => max(0, (int) ($totals['skipped'] ?? 0)),
                'errors' => max(0, (int) ($totals['errors'] ?? 0)),
                'api_calls' => max(0, (int) ($totals['api_calls'] ?? 0)),
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
     *     season: int,
     *     countries: list<array{id: int, name: string}>,
     *     next_country_index: int,
     *     completed: bool,
     *     totals: array{updated: int, skipped: int, errors: int, api_calls: int}
     * }|null
     */
    private function normalizeState(array $data): ?array
    {
        $countries = [];
        foreach ($data['countries'] ?? [] as $country) {
            if (!\is_array($country)) {
                continue;
            }

            $id = $country['id'] ?? null;
            $name = $country['name'] ?? null;
            if (!is_numeric($id) || !\is_string($name) || '' === trim($name)) {
                continue;
            }

            $countries[] = ['id' => (int) $id, 'name' => trim($name)];
        }

        if ([] === $countries) {
            return null;
        }

        return [
            'season' => (int) ($data['season'] ?? 0),
            'countries' => $countries,
            'next_country_index' => max(0, (int) ($data['next_country_index'] ?? 0)),
            'completed' => (bool) ($data['completed'] ?? false),
            'totals' => [
                'updated' => max(0, (int) ($data['totals']['updated'] ?? 0)),
                'skipped' => max(0, (int) ($data['totals']['skipped'] ?? 0)),
                'errors' => max(0, (int) ($data['totals']['errors'] ?? 0)),
                'api_calls' => max(0, (int) ($data['totals']['api_calls'] ?? 0)),
            ],
        ];
    }
}
