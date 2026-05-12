<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Fichier drapeau pour demander l arrêt d une synchro joueurs API-Sports en cours
 * (traité entre deux requêtes HTTP, pas au milieu d une requête).
 */
final class ApiFootballPlayerSyncStop
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function getFlagPath(): string
    {
        return $this->projectDir.'/var/api_football_player_sync.stop';
    }

    public function clear(): void
    {
        $path = $this->getFlagPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function requestStop(): void
    {
        $varDir = $this->projectDir.'/var';
        if (!is_dir($varDir)) {
            mkdir($varDir, 0775, true);
        }
        touch($this->getFlagPath());
    }

    public function isStopRequested(): bool
    {
        return is_file($this->getFlagPath());
    }
}
