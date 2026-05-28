<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client pour API-Sports Football v3 (https://v3.football.api-sports.io).
 *
 * Authentification : en-tête x-apisports-key (voir tableau de bord api-sports.io).
 *
 * Coupe du monde 2026 : voir le guide officiel API-Football / API-Sports
 * https://www.api-football.com/news/post/fifa-world-cup-2026-guide-to-using-data-with-api-sports
 * (identifiants CDM : league et saison via API_FOOTBALL_WC_LEAGUE_ID / API_FOOTBALL_WC_SEASON, voir {@see Wc2026SyncService}.)
 */
final class ApiFootballClient
{
    private const BASE_URL = 'https://v3.football.api-sports.io';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?string $apiKey,
        private readonly ApiFootballPlayerSyncStop $playerSyncStop,
        /** Délai entre deux requêtes consécutives (ms), pour respecter les quotas « par minute » (plans gratuits). 0 = aucune pause. */
        private readonly int $requestDelayMs = 4000,
    ) {
    }

    public function isConfigured(): bool
    {
        return null !== $this->apiKey && '' !== trim($this->apiKey);
    }

    /**
     * Pause entre appels API (synchro matchs / buts / joueurs).
     */
    public function applyInterRequestDelay(): void
    {
        $this->throttleBeforeNextRequest();
    }

    /**
     * @return list<array<string, mixed>> lignes brutes API (chaque élément contient souvent la clé "team").
     */
    public function fetchTeamsRowsForLeague(int $leagueId, int $season): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('API_FOOTBALL_KEY manquante.');
        }

        $data = $this->requestJson('/teams', ['league' => $leagueId, 'season' => $season]);
        $rows = $data['response'] ?? [];

        return \is_array($rows) ? $rows : [];
    }

    /**
     * Liste des équipes (sélections) d'une compétition, pour synchro joueurs par lots.
     *
     * @return list<array{id: int, name: string}>
     */
    public function fetchCompetitionTeamsForLeague(int $leagueId, int $season): array
    {
        $teams = [];
        foreach ($this->fetchTeamsRowsForLeague($leagueId, $season) as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $team = $row['team'] ?? $row;
            if (!\is_array($team)) {
                continue;
            }

            $rawTeamId = $team['id'] ?? null;
            $teamName = $this->normalizeString($team['name'] ?? null);
            if (!is_numeric($rawTeamId) || null === $teamName || '' === $teamName) {
                continue;
            }

            $teams[] = [
                'id' => (int) $rawTeamId,
                'name' => $teamName,
            ];
        }

        usort(
            $teams,
            static fn (array $a, array $b): int => strcmp($a['name'], $b['name']),
        );

        return $teams;
    }

    /**
     * @return list<array<string, mixed>> lignes brutes "fixture" (objet complet par match).
     */
    public function fetchFixturesForLeague(int $leagueId, int $season, int $maxHttpRequests): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('API_FOOTBALL_KEY manquante.');
        }

        if ($maxHttpRequests < 1) {
            return [];
        }

        $out = [];

        // L’API-Sports v3 rejette le paramètre `page` sur GET /fixtures avec league+season
        // (erreur : « The Page field do not exist. »). Une seule requête renvoie la liste
        // (pagination interne côté API sans ce paramètre).
        $data = $this->requestJson('/fixtures', [
            'league' => $leagueId,
            'season' => $season,
        ]);

        $batch = $data['response'] ?? [];
        if (!\is_array($batch)) {
            return [];
        }
        foreach ($batch as $row) {
            if (\is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null ligne "response" brute d’un match (fixture + teams + goals).
     */
    public function fetchFixtureById(int $fixtureId): ?array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('API_FOOTBALL_KEY manquante.');
        }

        $data = $this->requestJson('/fixtures', ['id' => $fixtureId]);
        $rows = $data['response'] ?? [];
        if (!\is_array($rows) || [] === $rows) {
            return null;
        }

        $row = $rows[0] ?? null;

        return \is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchFixtureEvents(int $fixtureId): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('API_FOOTBALL_KEY manquante.');
        }

        $data = $this->requestJson('/fixtures/events', ['fixture' => $fixtureId]);
        $rows = $data['response'] ?? [];

        return \is_array($rows) ? $rows : [];
    }

    /**
     * Récupère les joueurs des équipes d'une compétition (ex. CDM league=1, season=2026).
     * Chaque ligne est normalisée pour {@see Wc2026SyncService::syncButeurs} (firstname, lastname, name, photo, team_name).
     *
     * @param int|null $maxPlayersPerTeam plafond de joueurs importés par équipe (pays) ; null = sans limite
     *
     * @return array{rows: list<array<string, mixed>>, cancelled: bool}
     */
    public function fetchSquadPlayersForLeague(int $leagueId, int $season, int $maxHttpRequests, ?int $maxPlayersPerTeam = null): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException(
                'API_FOOTBALL_KEY manquante. Ajoutez-la dans .env.local (ne commitez jamais la clé).'
            );
        }

        if ($maxHttpRequests < 2) {
            throw new \InvalidArgumentException('maxHttpRequests doit être au moins 2 (équipes + au moins une page joueurs).');
        }

        $calls = 0;
        $teamsJson = $this->requestJson('/teams', ['league' => $leagueId, 'season' => $season]);
        ++$calls;

        $teams = $teamsJson['response'] ?? [];
        if (!\is_array($teams)) {
            throw new \UnexpectedValueException('Réponse API Football /teams invalide.');
        }

        $out = [];
        $cancelled = false;

        foreach ($teams as $row) {
            if ($this->playerSyncStop->isStopRequested()) {
                $cancelled = true;
                break;
            }

            if ($calls >= $maxHttpRequests) {
                break;
            }

            if (!\is_array($row)) {
                continue;
            }

            $team = $row['team'] ?? null;
            if (!\is_array($team)) {
                continue;
            }

            $rawTeamId = $team['id'] ?? null;
            $teamName = $this->normalizeString($team['name'] ?? null);
            if (!is_numeric($rawTeamId) || null === $teamName || '' === $teamName) {
                continue;
            }

            $teamId = (int) $rawTeamId;

            if (null !== $maxPlayersPerTeam && $maxPlayersPerTeam <= 0) {
                continue;
            }

            $chunk = $this->collectSquadPlayerRowsForTeam(
                $teamId,
                $teamName,
                $season,
                $calls,
                $maxHttpRequests,
                $maxPlayersPerTeam
            );
            $out = array_merge($out, $chunk['rows']);
            $calls = $chunk['calls'];
            if ($chunk['cancelled']) {
                $cancelled = true;
                break;
            }
        }

        return ['rows' => $out, 'cancelled' => $cancelled];
    }

    /**
     * Joueurs d’une seule équipe (pagination /players), sans requête /teams préalable.
     *
     * @param int|null $maxPlayersPerTeam null = toutes les pages renvoyées par l’API pour cette équipe
     *
     * @return array{rows: list<array<string, mixed>>, cancelled: bool}
     */
    public function fetchSquadPlayersForTeam(
        int $teamId,
        string $teamDisplayName,
        int $season,
        int $maxHttpRequests,
        ?int $maxPlayersPerTeam = null,
    ): array {
        if (!$this->isConfigured()) {
            throw new \RuntimeException(
                'API_FOOTBALL_KEY manquante. Ajoutez-la dans .env.local (ne commitez jamais la clé).'
            );
        }

        if ($maxHttpRequests < 1) {
            throw new \InvalidArgumentException('maxHttpRequests doit être au moins 1 pour la pagination /players.');
        }

        if (null !== $maxPlayersPerTeam && $maxPlayersPerTeam <= 0) {
            return ['rows' => [], 'cancelled' => false];
        }

        $chunk = $this->collectSquadPlayerRowsForTeam(
            $teamId,
            $teamDisplayName,
            $season,
            0,
            $maxHttpRequests,
            $maxPlayersPerTeam
        );

        return ['rows' => $chunk['rows'], 'cancelled' => $chunk['cancelled']];
    }

    /**
     * Récupère la sélection compétition d'une équipe (endpoint /players/squads).
     * Cette liste correspond aux joueurs convoqués par la sélection nationale.
     *
     * @return array{rows: list<array<string, mixed>>, cancelled: bool}
     */
    public function fetchCompetitionSquadPlayersForTeam(
        int $teamId,
        string $teamDisplayName,
    ): array {
        if (!$this->isConfigured()) {
            throw new \RuntimeException(
                'API_FOOTBALL_KEY manquante. Ajoutez-la dans .env.local (ne commitez jamais la clé).'
            );
        }

        if ($this->playerSyncStop->isStopRequested()) {
            return ['rows' => [], 'cancelled' => true];
        }

        $this->throttleBeforeNextRequest();
        $data = $this->requestJson('/players/squads', ['team' => $teamId]);

        $rows = [];
        $response = $data['response'] ?? [];
        if (!\is_array($response)) {
            return ['rows' => [], 'cancelled' => false];
        }

        foreach ($response as $squadRow) {
            if (!\is_array($squadRow)) {
                continue;
            }

            $players = $squadRow['players'] ?? null;
            if (!\is_array($players)) {
                continue;
            }

            foreach ($players as $player) {
                if (!\is_array($player)) {
                    continue;
                }

                $fullName = $this->normalizeString($player['name'] ?? null);
                $firstName = null;
                $lastName = null;
                if (null !== $fullName) {
                    $parts = preg_split('/\s+/', $fullName) ?: [];
                    if ([] !== $parts) {
                        $firstName = trim((string) ($parts[0] ?? ''));
                        $firstName = '' !== $firstName ? $firstName : null;
                    }
                    if (\count($parts) > 1) {
                        array_shift($parts);
                        $tmpLastName = trim(implode(' ', $parts));
                        $lastName = '' !== $tmpLastName ? $tmpLastName : null;
                    }
                }

                $rawPlayerId = $player['id'] ?? null;
                $rows[] = [
                    'firstname' => $firstName,
                    'lastname' => $lastName,
                    'name' => $fullName,
                    'photo' => $this->normalizeString($player['photo'] ?? null),
                    'team_name' => $teamDisplayName,
                    'api_sports_player_id' => is_numeric($rawPlayerId) ? (int) $rawPlayerId : null,
                ];
            }
        }

        return ['rows' => $rows, 'cancelled' => false];
    }

    /**
     * Sélections compétition de toutes les équipes d'une ligue (1× /teams + 1× /players/squads par équipe).
     *
     * @param int|null $maxPlayersPerTeam plafond optionnel par équipe (null = effectif complet renvoyé par l’API)
     *
     * @return array{rows: list<array<string, mixed>>, cancelled: bool}
     */
    public function fetchCompetitionSquadPlayersForLeague(
        int $leagueId,
        int $season,
        int $maxHttpRequests,
        ?int $maxPlayersPerTeam = null,
    ): array {
        if (!$this->isConfigured()) {
            throw new \RuntimeException(
                'API_FOOTBALL_KEY manquante. Ajoutez-la dans .env.local (ne commitez jamais la clé).'
            );
        }

        if ($maxHttpRequests < 2) {
            throw new \InvalidArgumentException('maxHttpRequests doit être au moins 2 (équipes + au moins une sélection).');
        }

        $calls = 0;
        $teamsJson = $this->requestJson('/teams', ['league' => $leagueId, 'season' => $season]);
        ++$calls;

        $teams = $teamsJson['response'] ?? [];
        if (!\is_array($teams)) {
            throw new \UnexpectedValueException('Réponse API Football /teams invalide.');
        }

        $out = [];
        $cancelled = false;

        foreach ($teams as $row) {
            if ($this->playerSyncStop->isStopRequested()) {
                $cancelled = true;
                break;
            }

            if ($calls >= $maxHttpRequests) {
                break;
            }

            if (!\is_array($row)) {
                continue;
            }

            $team = $row['team'] ?? null;
            if (!\is_array($team)) {
                continue;
            }

            $rawTeamId = $team['id'] ?? null;
            $teamName = $this->normalizeString($team['name'] ?? null);
            if (!is_numeric($rawTeamId) || null === $teamName || '' === $teamName) {
                continue;
            }

            $chunk = $this->fetchCompetitionSquadPlayersForTeam((int) $rawTeamId, $teamName);
            ++$calls;

            if ($chunk['cancelled']) {
                $cancelled = true;
                break;
            }

            $teamRows = $chunk['rows'];
            if (null !== $maxPlayersPerTeam && $maxPlayersPerTeam > 0) {
                $teamRows = \array_slice($teamRows, 0, $maxPlayersPerTeam);
            }

            $out = array_merge($out, $teamRows);
        }

        return ['rows' => $out, 'cancelled' => $cancelled];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, cancelled: bool, calls: int}
     */
    private function collectSquadPlayerRowsForTeam(
        int $teamId,
        string $teamName,
        int $season,
        int $callsSoFar,
        int $maxHttpRequests,
        ?int $maxPlayersPerTeam,
    ): array {
        $out = [];
        $cancelled = false;
        $calls = $callsSoFar;
        $page = 1;
        $totalPages = 1;
        $playersAddedForTeam = 0;

        do {
            if ($this->playerSyncStop->isStopRequested()) {
                $cancelled = true;
                break;
            }

            if ($calls >= $maxHttpRequests) {
                break;
            }

            $this->throttleBeforeNextRequest();

            if ($this->playerSyncStop->isStopRequested()) {
                $cancelled = true;
                break;
            }

            $playersJson = $this->requestJson('/players', [
                'team' => $teamId,
                'season' => $season,
                'page' => $page,
            ]);
            ++$calls;

            $paging = $playersJson['paging'] ?? null;
            if (\is_array($paging)) {
                $current = (int) ($paging['current'] ?? $page);
                $totalPages = max(1, (int) ($paging['total'] ?? 1));
                if ($current !== $page) {
                    $page = $current;
                }
            }

            $items = $playersJson['response'] ?? [];
            if (!\is_array($items) || [] === $items) {
                break;
            }

            foreach ($items as $item) {
                if (null !== $maxPlayersPerTeam && $playersAddedForTeam >= $maxPlayersPerTeam) {
                    break 2;
                }

                if (!\is_array($item)) {
                    continue;
                }

                $player = $item['player'] ?? null;
                if (!\is_array($player)) {
                    continue;
                }

                $pid = $player['id'] ?? null;
                $out[] = [
                    'firstname' => $this->normalizeString($player['firstname'] ?? null),
                    'lastname' => $this->normalizeString($player['lastname'] ?? null),
                    'name' => $this->normalizeString($player['name'] ?? null),
                    'photo' => $this->normalizeString($player['photo'] ?? null),
                    'team_name' => $teamName,
                    'api_sports_player_id' => is_numeric($pid) ? (int) $pid : null,
                ];
                ++$playersAddedForTeam;
            }

            if (null !== $maxPlayersPerTeam && $playersAddedForTeam >= $maxPlayersPerTeam) {
                break;
            }

            ++$page;
        } while ($page <= $totalPages);

        return ['rows' => $out, 'cancelled' => $cancelled, 'calls' => $calls];
    }

    private function throttleBeforeNextRequest(): void
    {
        if ($this->requestDelayMs <= 0) {
            return;
        }

        usleep($this->requestDelayMs * 1000);
    }

    /**
     * @param array<string, scalar|null> $query
     *
     * @return array<string, mixed>
     */
    private function requestJson(string $path, array $query): array
    {
        $lastException = null;
        $maxAttempts = 4;

        for ($i = 0; $i < $maxAttempts; ++$i) {
            if ($i > 0) {
                sleep(5 * $i);
            }

            try {
                return $this->performRequestJson($path, $query);
            } catch (\RuntimeException $e) {
                $lastException = $e;
                $msg = $e->getMessage();
                if (!str_contains($msg, 'rateLimit') && !str_contains($msg, 'Too many requests')) {
                    throw $e;
                }
            }
        }

        throw $lastException ?? new \RuntimeException('API Football : échec après plusieurs tentatives (rate limit).');
    }

    /**
     * @param array<string, scalar|null> $query
     *
     * @return array<string, mixed>
     */
    private function performRequestJson(string $path, array $query): array
    {
        $response = $this->httpClient->request('GET', self::BASE_URL.$path, [
            'headers' => [
                'x-apisports-key' => trim((string) $this->apiKey),
                'Accept' => 'application/json',
            ],
            'query' => $query,
        ]);

        $data = $response->toArray(false);
        if (!\is_array($data)) {
            throw new \UnexpectedValueException(sprintf('Réponse non-JSON sur API Football %s.', $path));
        }

        $errors = $data['errors'] ?? [];
        if ([] !== $errors && null !== $errors) {
            $message = \is_array($errors) ? json_encode($errors, JSON_UNESCAPED_UNICODE) : (string) $errors;

            $hint = '';
            if (str_contains($message, 'Free plans') || str_contains($message, 'do not have access to this season')) {
                $hint = ' Les offres gratuites n’ont en général pas la saison 2026 : pour tester la synchro, ajoutez dans .env.local '
                    .'API_FOOTBALL_WC_SEASON=2022 (Coupe du monde 2022), ou passez au plan payant pour la CDM 2026. '
                    .'Puis : php bin/console cache:clear.';
            }
            if (str_contains($message, 'rateLimit') || str_contains($message, 'Too many requests')) {
                $hint = ' Augmentez API_FOOTBALL_REQUEST_DELAY_MS (ex. 6000 ou 8000 dans .env.local), attendez une minute, ou passez à un plan avec une limite par minute plus haute.';
            }

            throw new \RuntimeException(sprintf('Erreur API Football %s: %s%s', $path, $message, $hint));
        }

        return $data;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
