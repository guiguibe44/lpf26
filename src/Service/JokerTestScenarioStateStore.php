<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Persistance locale de l'étape du scénario jokers (fichier var/).
 */
final class JokerTestScenarioStateStore
{
    private const FILENAME = 'joker_test_scenario_state.json';

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function clear(): void
    {
        $path = $this->getFilePath();
        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @param array{
     *     step_index: int,
     *     match_ids: list<int>,
     *     team_ids: array<string, int>,
     *     buteur_ids: array<string, int>,
     *     seeded_at: string
     * } $state
     */
    public function write(array $state): void
    {
        $path = $this->getFilePath();
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, json_encode($state, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));
    }

    /**
     * @return array{
     *     step_index: int,
     *     match_ids: list<int>,
     *     team_ids: array<string, int>,
     *     buteur_ids: array<string, int>,
     *     seeded_at: string
     * }|null
     */
    public function read(): ?array
    {
        $path = $this->getFilePath();
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if (false === $raw || '' === trim($raw)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        if (!isset($decoded['match_ids'], $decoded['team_ids'], $decoded['step_index'])) {
            return null;
        }

        return [
            'step_index' => (int) $decoded['step_index'],
            'match_ids' => array_map(intval(...), (array) $decoded['match_ids']),
            'team_ids' => array_map(intval(...), (array) $decoded['team_ids']),
            'buteur_ids' => array_map(intval(...), (array) ($decoded['buteur_ids'] ?? [])),
            'seeded_at' => (string) ($decoded['seeded_at'] ?? ''),
        ];
    }

    public function getStepIndex(): int
    {
        return $this->read()['step_index'] ?? 0;
    }

    public function setStepIndex(int $index): void
    {
        $state = $this->read();
        if (null === $state) {
            throw new \RuntimeException('Scénario jokers non initialisé. Lancez app:joker-test:setup.');
        }

        $state['step_index'] = max(0, $index);
        $this->write($state);
    }

    private function getFilePath(): string
    {
        return $this->projectDir.'/var/'.self::FILENAME;
    }
}
