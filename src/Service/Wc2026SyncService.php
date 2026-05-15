<?php

declare(strict_types=1);

namespace App\Service;

use App\Data\WorldCup2026Groups;
use App\Entity\But;
use App\Entity\Buteur;
use App\Entity\Country;
use App\Entity\GameMatch;
use App\Repository\ButeurRepository;
use App\Repository\ButRepository;
use App\Repository\CountryRepository;
use App\Repository\GameMatchRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Synchronisation compétition (pays, matchs, joueurs, buts) via API-Sports Football v3 uniquement.
 *
 * @see https://www.api-football.com/news/post/fifa-world-cup-2026-guide-to-using-data-with-api-sports
 */
final class Wc2026SyncService
{
    public function __construct(
        private readonly ApiFootballClient $apiFootballClient,
        private readonly ApiFootballPlayerSyncStop $apiFootballPlayerSyncStop,
        private readonly ButeurRepository $buteurRepository,
        private readonly ButRepository $butRepository,
        private readonly CountryRepository $countryRepository,
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PronosticScoringService $pronosticScoringService,
        private readonly CountryFlagStorage $countryFlagStorage,
        private readonly ButeurPhotoStorage $buteurPhotoStorage,
        private readonly int $apiFootballWorldCupLeagueId = 1,
        private readonly int $apiFootballWorldCupSeason = 2026,
        /** Nombre max de requêtes /fixtures/events pour une synchro buts (0 = désactivé). */
        private readonly int $apiFootballSyncGoalsMaxRequests = 300,
    ) {
    }

    /**
     * @return array{created:int, updated:int, flags_downloaded:int}
     */
    public function syncCountries(int $limit = 500): array
    {
        $this->assertApiFootballConfigured();

        $rows = $this->apiFootballClient->fetchTeamsRowsForLeague(
            $this->apiFootballWorldCupLeagueId,
            $this->apiFootballWorldCupSeason
        );

        $countries = $this->indexCountriesByName();
        $created = 0;
        $updated = 0;
        $flagsDownloaded = 0;
        $n = 0;

        foreach ($rows as $row) {
            if (++$n > $limit) {
                break;
            }

            if (!\is_array($row)) {
                continue;
            }

            $team = $row['team'] ?? $row;
            if (!\is_array($team)) {
                continue;
            }

            $name = trim((string) ($team['name'] ?? ''));
            if ('' === $name) {
                continue;
            }

            $flagUrl = $this->normalizeNullableString($team['logo'] ?? null);
            $key = $this->normalizeNameKey($name);
            $country = $countries[$key] ?? null;

            if (!$country instanceof Country) {
                $country = (new Country())->setNom($name);
                $this->applyCountryFlag($country, $flagUrl);
                $this->entityManager->persist($country);
                $countries[$key] = $country;
                ++$created;
                if ($this->countryFlagStorage->isLocalUpload($country->getDrapeau())) {
                    ++$flagsDownloaded;
                }

                continue;
            }

            $changed = false;
            if ($country->getNom() !== $name) {
                $country->setNom($name);
                $changed = true;
            }
            if ($this->applyCountryFlag($country, $flagUrl)) {
                $changed = true;
                if ($this->countryFlagStorage->isLocalUpload($country->getDrapeau())) {
                    ++$flagsDownloaded;
                }
            }

            if ($changed) {
                ++$updated;
            }
        }

        $this->entityManager->flush();

        return [
            'created' => $created,
            'updated' => $updated,
            'flags_downloaded' => $flagsDownloaded,
        ];
    }

    /**
     * Télécharge les drapeaux dont l’URL est encore distante (http/https).
     *
     * @return array{downloaded:int, skipped:int, failed:int}
     */
    public function downloadAllCountryFlags(): array
    {
        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->countryRepository->findAll() as $country) {
            if ($this->countryFlagStorage->isLocalUpload($country->getDrapeau())) {
                ++$skipped;

                continue;
            }

            if (!$this->countryFlagStorage->isRemoteUrl($country->getDrapeau())) {
                ++$skipped;

                continue;
            }

            if ($this->countryFlagStorage->storeFlagForCountry($country)) {
                ++$downloaded;
            } else {
                ++$failed;
            }
        }

        $this->entityManager->flush();

        return [
            'downloaded' => $downloaded,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    /**
     * Télécharge les photos de buteurs dont l’URL est encore distante (http/https).
     *
     * @return array{downloaded:int, skipped:int, failed:int}
     */
    public function downloadAllButeurPhotos(): array
    {
        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($this->buteurRepository->findAll() as $buteur) {
            if ($this->buteurPhotoStorage->isLocalUpload($buteur->getPhoto())) {
                ++$skipped;

                continue;
            }

            if (!$this->buteurPhotoStorage->isRemoteUrl($buteur->getPhoto())) {
                ++$skipped;

                continue;
            }

            if ($this->buteurPhotoStorage->storePhotoForButeur($buteur)) {
                ++$downloaded;
            } else {
                ++$failed;
            }
        }

        $this->entityManager->flush();

        return [
            'downloaded' => $downloaded,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    /**
     * Recalcule la phase « Group X » des matchs à partir des noms d’équipes et du champ pays.groupe.
     *
     * @return array{updated:int}
     */
    public function repairGroupMatchPhases(): array
    {
        $updated = 0;

        foreach ($this->gameMatchRepository->findAll() as $match) {
            $home = $match->getPaysDomicile();
            $away = $match->getPaysExterieur();
            if (!$home instanceof Country || !$away instanceof Country) {
                continue;
            }

            $newPhase = $this->resolveGroupPhaseForCountries($home, $away);
            if (null === $newPhase) {
                continue;
            }

            $currentLetter = GameMatch::extractGroupStandingLetter($match->getPhase());
            $newLetter = GameMatch::extractGroupStandingLetter($newPhase);
            if ($currentLetter === $newLetter && null !== $currentLetter) {
                continue;
            }

            if ($this->isGenericStageRoundLabel($match->getPhase()) || null === $currentLetter) {
                $match->setPhase($newPhase);
                ++$updated;
            }
        }

        $this->entityManager->flush();

        return ['updated' => $updated];
    }

    private function resolveGroupPhaseForCountries(Country $home, Country $away): ?string
    {
        $homeGroup = $home->getGroupe();
        $awayGroup = $away->getGroupe();
        if (null !== $homeGroup && $homeGroup === $awayGroup) {
            return 'Group '.$homeGroup;
        }

        return WorldCup2026Groups::resolveGroupPhase(
            (string) $home->getNom(),
            (string) $away->getNom(),
        );
    }

    /**
     * @return array{created:int, updated:int, skipped:int}
     */
    public function syncMatches(int $limit = 500): array
    {
        $this->assertApiFootballConfigured();

        $fixtureRows = $this->apiFootballClient->fetchFixturesForLeague(
            $this->apiFootballWorldCupLeagueId,
            $this->apiFootballWorldCupSeason,
            max(1, $limit)
        );

        $countries = $this->indexCountriesByName();
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($fixtureRows as $row) {
            if (!\is_array($row)) {
                ++$skipped;
                continue;
            }

            $parsed = $this->parseApiFootballFixtureRow($row);
            if (null === $parsed) {
                ++$skipped;
                continue;
            }

            $homeName = $parsed['home_name'];
            $awayName = $parsed['away_name'];
            $kickoffAt = $parsed['kickoff_at'];
            $fixtureApiId = $parsed['fixture_api_id'];

            if ($this->normalizeNameKey($homeName) === $this->normalizeNameKey($awayName)) {
                ++$skipped;
                continue;
            }

            $homeCountry = $this->findOrCreateCountry($countries, $homeName, $parsed['home_logo']);
            $awayCountry = $this->findOrCreateCountry($countries, $awayName, $parsed['away_logo']);

            if (null === $homeCountry || null === $awayCountry) {
                ++$skipped;
                continue;
            }

            $match = $this->gameMatchRepository->findOneByApiFootballFixtureId($fixtureApiId);
            if (!$match instanceof GameMatch) {
                $match = $this->gameMatchRepository->findOneBy([
                    'paysDomicile' => $homeCountry,
                    'paysExterieur' => $awayCountry,
                    'dateHeure' => $kickoffAt,
                ]) ?? new GameMatch();
            }

            $isNew = null === $match->getId();
            $oldScoreDomicile = $match->getScoreDomicile();
            $oldScoreExterieur = $match->getScoreExterieur();

            $match
                ->setApiFootballFixtureId($fixtureApiId)
                ->setPaysDomicile($homeCountry)
                ->setPaysExterieur($awayCountry)
                ->setDateHeure($kickoffAt)
                ->setPhase($this->resolveMatchPhaseForSync($parsed['round'], $homeName, $awayName))
                ->setStatut($this->mapApiFootballFixtureStatus($parsed['status_short']))
                ->setScoreDomicile($parsed['score_home'])
                ->setScoreExterieur($parsed['score_away'])
                ->setVenueName($parsed['venue_name'])
                ->setReferee($parsed['referee']);

            if ($isNew) {
                $this->entityManager->persist($match);
                ++$created;
            } else {
                ++$updated;
            }

            if (($oldScoreDomicile !== $match->getScoreDomicile()) || ($oldScoreExterieur !== $match->getScoreExterieur())) {
                $this->pronosticScoringService->rescoreForMatch($match);
            }
        }

        $this->entityManager->flush();

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @param int|null $maxPlayersPerTeam nombre max de joueurs à importer par équipe (pays) ; null = tous les joueurs retournés par l’API
     *
     * @return array{created:int, updated:int, skipped:int, cancelled:bool}
     */
    public function syncButeurs(int $limit = 1000, ?int $maxPlayersPerTeam = null): array
    {
        $this->assertApiFootballConfigured();

        $this->apiFootballPlayerSyncStop->clear();

        $maxRequests = max(5, min($limit, 5000));
        $result = $this->apiFootballClient->fetchSquadPlayersForLeague(
            $this->apiFootballWorldCupLeagueId,
            $this->apiFootballWorldCupSeason,
            $maxRequests,
            $maxPlayersPerTeam
        );

        $import = $this->importButeursFromNormalizedList($result['rows']);
        if ($result['cancelled']) {
            $this->apiFootballPlayerSyncStop->clear();
        }

        return array_merge($import, ['cancelled' => $result['cancelled']]);
    }

    /**
     * Synchronise les joueurs d’un seul pays (équipe CDM) via l’API. Le nom du pays en base doit correspondre à une équipe /teams de la ligue (ex. après sync pays).
     *
     * @param int|null $maxPlayersPerTeam null = tous les joueurs renvoyés par l’API pour cette équipe
     *
     * @return array{created:int, updated:int, skipped:int, cancelled:bool}
     */
    public function syncButeursForCountry(int $countryId, int $maxHttpRequests, ?int $maxPlayersPerTeam = null): array
    {
        $this->assertApiFootballConfigured();

        $this->apiFootballPlayerSyncStop->clear();

        $country = $this->countryRepository->find($countryId);
        if (!$country instanceof Country) {
            throw new \InvalidArgumentException('Pays introuvable.');
        }

        $rows = $this->apiFootballClient->fetchTeamsRowsForLeague(
            $this->apiFootballWorldCupLeagueId,
            $this->apiFootballWorldCupSeason
        );

        $targetKey = $this->normalizeNameKey((string) $country->getNom());
        $teamId = null;
        $teamName = null;

        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }

            $team = $row['team'] ?? $row;
            if (!\is_array($team)) {
                continue;
            }

            $name = trim((string) ($team['name'] ?? ''));
            if ('' === $name) {
                continue;
            }

            if ($this->normalizeNameKey($name) !== $targetKey) {
                continue;
            }

            $rawId = $team['id'] ?? null;
            if (!is_numeric($rawId)) {
                throw new \RuntimeException(sprintf('Réponse API invalide pour l’équipe « %s ».', $name));
            }

            $teamId = (int) $rawId;
            $teamName = $name;
            break;
        }

        if (null === $teamId || null === $teamName) {
            throw new \RuntimeException(
                sprintf(
                    'Aucune équipe de la compétition ne correspond au pays « %s ». Vérifiez le nom (création manuelle) ou lancez d’abord la synchro des pays.',
                    $country->getNom()
                )
            );
        }

        $cap = max(5, min($maxHttpRequests, 5000));
        $result = $this->apiFootballClient->fetchSquadPlayersForTeam(
            $teamId,
            $teamName,
            $this->apiFootballWorldCupSeason,
            $cap,
            $maxPlayersPerTeam
        );

        $import = $this->importButeursFromNormalizedList($result['rows']);
        if ($result['cancelled']) {
            $this->apiFootballPlayerSyncStop->clear();
        }

        return array_merge($import, ['cancelled' => $result['cancelled']]);
    }

    /**
     * Importe les buts depuis /fixtures/events (types Goal). Requiert des buteurs avec api_sports_player_id renseigné.
     *
     * @return array{created:int, skipped:int, api_calls:int}
     */
    public function syncButsFromFixtureEvents(): array
    {
        $this->assertApiFootballConfigured();

        if ($this->apiFootballSyncGoalsMaxRequests <= 0) {
            return ['created' => 0, 'skipped' => 0, 'api_calls' => 0];
        }

        $matches = $this->gameMatchRepository->findWithFixtureIdForEventsSync();
        $created = 0;
        $skipped = 0;
        $apiCalls = 0;

        foreach ($matches as $match) {
            if ($apiCalls >= $this->apiFootballSyncGoalsMaxRequests) {
                break;
            }

            if ($this->apiFootballPlayerSyncStop->isStopRequested()) {
                break;
            }

            if ($apiCalls > 0) {
                $this->apiFootballClient->applyInterRequestDelay();
            }

            $fixtureId = $match->getApiFootballFixtureId();
            if (null === $fixtureId) {
                continue;
            }

            $events = $this->apiFootballClient->fetchFixtureEvents($fixtureId);
            ++$apiCalls;

            $goalOrdinal = 0;
            foreach ($events as $event) {
                if (!\is_array($event)) {
                    ++$skipped;
                    continue;
                }

                $type = strtoupper(trim((string) ($event['type'] ?? '')));
                if ('GOAL' !== $type) {
                    continue;
                }

                $player = $event['player'] ?? null;
                if (!\is_array($player)) {
                    ++$skipped;
                    continue;
                }

                $playerId = $player['id'] ?? null;
                if (!is_numeric($playerId)) {
                    ++$skipped;
                    continue;
                }

                $playerId = (int) $playerId;
                $buteur = $this->buteurRepository->findOneByApiSportsPlayerId($playerId);
                if (!$buteur instanceof Buteur) {
                    ++$skipped;
                    continue;
                }

                $time = $event['time'] ?? null;
                $elapsed = \is_array($time) ? (int) ($time['elapsed'] ?? 0) : 0;
                $extra = \is_array($time) ? (int) ($time['extra'] ?? 0) : 0;
                $eventKey = sprintf('f%d-g%d-p%d-e%d-x%d', $fixtureId, $goalOrdinal, $playerId, $elapsed, $extra);
                ++$goalOrdinal;

                $existing = $this->butRepository->findOneBy(['apiSportsEventKey' => $eventKey]);
                if ($existing instanceof But) {
                    continue;
                }

                $but = (new But())
                    ->setButeur($buteur)
                    ->setMatchRef($match)
                    ->setMinute($elapsed > 0 ? $elapsed : null)
                    ->setPointsAttribues(0)
                    ->setApiSportsEventKey($eventKey);
                $this->entityManager->persist($but);
                ++$created;
            }
        }

        $this->entityManager->flush();

        return ['created' => $created, 'skipped' => $skipped, 'api_calls' => $apiCalls];
    }

    private function assertApiFootballConfigured(): void
    {
        if (!$this->apiFootballClient->isConfigured()) {
            throw new \RuntimeException(
                'API_FOOTBALL_KEY absente ou vide. Toute la synchro passe par API-Sports : '
                .'configurez la clé dans .env.local puis php bin/console cache:clear.'
            );
        }
    }

    /**
     * @param array<string, mixed> $row une entrée "response" de /fixtures
     *
     * @return array{
     *     fixture_api_id: int,
     *     home_name: string,
     *     away_name: string,
     *     home_logo: ?string,
     *     away_logo: ?string,
     *     kickoff_at: \DateTimeImmutable,
     *     status_short: string,
     *     score_home: ?int,
     *     score_away: ?int,
     *     round: ?string,
     *     venue_name: ?string,
     *     referee: ?string
     * }|null
     */
    private function parseApiFootballFixtureRow(array $row): ?array
    {
        $fixture = $row['fixture'] ?? null;
        if (!\is_array($fixture)) {
            return null;
        }

        $fid = $fixture['id'] ?? null;
        if (!is_numeric($fid)) {
            return null;
        }

        $dateRaw = $fixture['date'] ?? null;
        if (!\is_string($dateRaw) || '' === trim($dateRaw)) {
            return null;
        }

        try {
            $kickoffAt = new \DateTimeImmutable($dateRaw);
        } catch (\Throwable) {
            return null;
        }

        $status = $fixture['status'] ?? null;
        $statusShort = \is_array($status) ? (string) ($status['short'] ?? '') : '';

        $home = $this->extractTeamFromFixtureTeams($row['teams'] ?? null, 'home');
        $away = $this->extractTeamFromFixtureTeams($row['teams'] ?? null, 'away');

        if (null === $home || null === $away) {
            return null;
        }

        $goals = $row['goals'] ?? null;
        $scoreHome = null;
        $scoreAway = null;
        if (\is_array($goals)) {
            if (isset($goals['home']) && null !== $goals['home'] && '' !== $goals['home']) {
                $scoreHome = (int) $goals['home'];
            }
            if (isset($goals['away']) && null !== $goals['away'] && '' !== $goals['away']) {
                $scoreAway = (int) $goals['away'];
            }
        }

        $league = $row['league'] ?? null;
        $round = \is_array($league) ? $this->normalizeNullableString($league['round'] ?? null) : null;

        $venue = $fixture['venue'] ?? null;
        $venueName = \is_array($venue) ? $this->normalizeNullableString($venue['name'] ?? null) : null;

        $referee = $this->normalizeNullableString(
            \is_string($fixture['referee'] ?? null) ? $fixture['referee'] : null
        );

        return [
            'fixture_api_id' => (int) $fid,
            'home_name' => $home['name'],
            'away_name' => $away['name'],
            'home_logo' => $home['logo'],
            'away_logo' => $away['logo'],
            'kickoff_at' => $kickoffAt,
            'status_short' => $statusShort,
            'score_home' => $scoreHome,
            'score_away' => $scoreAway,
            'round' => $round,
            'venue_name' => $venueName,
            'referee' => $referee,
        ];
    }

    /**
     * @return array{name: string, logo: ?string}|null
     */
    private function extractTeamFromFixtureTeams(mixed $teams, string $side): ?array
    {
        if (!\is_array($teams)) {
            return null;
        }

        $node = $teams[$side] ?? null;
        if (!\is_array($node)) {
            return null;
        }

        if (isset($node['team']) && \is_array($node['team'])) {
            $node = $node['team'];
        }

        $name = $this->normalizeNullableString($node['name'] ?? null);
        if (null === $name) {
            return null;
        }

        $logo = $this->normalizeNullableString($node['logo'] ?? null);

        return ['name' => $name, 'logo' => $logo];
    }

    private function mapApiFootballFixtureStatus(string $short): string
    {
        $s = mb_strtoupper(trim($short));

        return match ($s) {
            'FT', 'AET', 'PEN' => 'FINISHED',
            'LIVE', '1H', '2H', 'HT', 'ET', 'BT', 'INT' => 'LIVE',
            'PST' => 'POSTPONED',
            'CANC', 'ABD', 'AWD', 'WO' => 'CANCELLED',
            default => 'SCHEDULED',
        };
    }

    /**
     * L’API renvoie souvent « Group Stage » / « League Stage » : sans lettre de groupe,
     * le classement regroupe tout sous « Stage ». On normalise en « Group A »…« Group L »
     * via la grille CDM 2026 quand les deux équipes sont dans le même groupe.
     */
    private function resolveMatchPhaseForSync(?string $apiRound, string $homeTeamName, string $awayTeamName): ?string
    {
        $fromApi = $this->normalizeGroupLetterPhaseFromApiRound($apiRound);
        if (null !== $fromApi) {
            return $fromApi;
        }

        $fromGrid = WorldCup2026Groups::resolveGroupPhase($homeTeamName, $awayTeamName);
        if (null !== $fromGrid) {
            return $fromGrid;
        }

        if ($this->isGenericStageRoundLabel($apiRound)) {
            return null;
        }

        return $this->normalizeNullableString($apiRound);
    }

    /**
     * N’accepte qu’un groupe explicite A–L (CDM 2026 à 12 groupes), ex. « Group A », « Group A - 1 ».
     */
    private function normalizeGroupLetterPhaseFromApiRound(?string $round): ?string
    {
        $raw = $this->normalizeNullableString($round);
        if (null === $raw) {
            return null;
        }

        if (1 === preg_match('/^Group\s+([A-L])\b/iu', trim($raw), $m)) {
            return 'Group '.mb_strtoupper($m[1]);
        }

        if (1 === preg_match('/\bGroup\s+([A-L])\b/iu', trim($raw), $m)) {
            return 'Group '.mb_strtoupper($m[1]);
        }

        return null;
    }

    private function isGenericStageRoundLabel(?string $round): bool
    {
        $raw = $this->normalizeNullableString($round);
        if (null === $raw) {
            return false;
        }

        $r = mb_strtolower($raw);

        return str_contains($r, 'group stage')
            || str_contains($r, 'league stage')
            || str_contains($r, '1st stage')
            || $r === 'stage';
    }

    /**
     * @param list<array<string, mixed>> $apiPlayers
     *
     * @return array{created:int, updated:int, skipped:int}
     */
    private function importButeursFromNormalizedList(array $apiPlayers): array
    {
        $countries = $this->indexCountriesByName();
        $indexedButeurs = $this->indexButeursByIdentity();
        $indexedByApiId = [];
        foreach ($this->buteurRepository->findAll() as $b) {
            $apid = $b->getApiSportsPlayerId();
            if (null !== $apid) {
                $indexedByApiId[$apid] = $b;
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($apiPlayers as $apiPlayer) {
            if (!\is_array($apiPlayer)) {
                ++$skipped;
                continue;
            }

            $prenom = $this->extractPlayerFirstName($apiPlayer);
            $nom = $this->extractPlayerLastName($apiPlayer);
            $countryName = $this->extractPlayerCountryName($apiPlayer);
            $photo = $this->normalizeNullableString($apiPlayer['photo_url'] ?? $apiPlayer['photo'] ?? null);
            $apiPlayerId = isset($apiPlayer['api_sports_player_id']) && is_numeric($apiPlayer['api_sports_player_id'])
                ? (int) $apiPlayer['api_sports_player_id']
                : null;

            if (null === $countryName) {
                ++$skipped;
                continue;
            }

            if ((null === $prenom || '' === $prenom) && (null === $nom || '' === $nom)) {
                ++$skipped;
                continue;
            }

            $country = $this->findOrCreateCountry($countries, $countryName, null);
            if (!$country instanceof Country) {
                ++$skipped;
                continue;
            }

            $buteur = null;
            if (null !== $apiPlayerId && isset($indexedByApiId[$apiPlayerId])) {
                $buteur = $indexedByApiId[$apiPlayerId];
            }

            $identityKey = $this->buildButeurIdentityKey($prenom ?? '', $nom ?? '', $country);
            if (!$buteur instanceof Buteur) {
                $buteur = $indexedButeurs[$identityKey] ?? null;
            }

            if (!$buteur instanceof Buteur) {
                $buteur = new Buteur();
                $buteur
                    ->setPrenom($prenom ?: '-')
                    ->setNom($nom ?: '-')
                    ->setPays($country);
                $this->applyButeurPhoto($buteur, $photo);
                if (null !== $apiPlayerId) {
                    $buteur->setApiSportsPlayerId($apiPlayerId);
                }
                $this->entityManager->persist($buteur);
                $indexedButeurs[$identityKey] = $buteur;
                if (null !== $apiPlayerId) {
                    $indexedByApiId[$apiPlayerId] = $buteur;
                }
                ++$created;

                continue;
            }

            $changed = false;
            if (null !== $prenom && '' !== $prenom && $buteur->getPrenom() !== $prenom) {
                $buteur->setPrenom($prenom);
                $changed = true;
            }
            if (null !== $nom && '' !== $nom && $buteur->getNom() !== $nom) {
                $buteur->setNom($nom);
                $changed = true;
            }
            if ($buteur->getPays()?->getId() !== $country->getId()) {
                $buteur->setPays($country);
                $changed = true;
            }
            if ($this->applyButeurPhoto($buteur, $photo)) {
                $changed = true;
            }
            if (null !== $apiPlayerId && $buteur->getApiSportsPlayerId() !== $apiPlayerId) {
                $buteur->setApiSportsPlayerId($apiPlayerId);
                $changed = true;
            }

            if ($changed) {
                ++$updated;
            }
        }

        $this->entityManager->flush();

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /**
     * @return array<string, Country>
     */
    private function indexCountriesByName(): array
    {
        $indexed = [];
        foreach ($this->countryRepository->findAll() as $country) {
            $indexed[$this->normalizeNameKey((string) $country->getNom())] = $country;
        }

        return $indexed;
    }

    /**
     * @param array<string, Country> $countries
     */
    private function findOrCreateCountry(array &$countries, string $name, ?string $flag): ?Country
    {
        $key = $this->normalizeNameKey($name);
        if ('' === $key) {
            return null;
        }

        $country = $countries[$key] ?? null;
        if (!$country instanceof Country) {
            $country = (new Country())->setNom($name);
            $this->applyCountryFlag($country, $flag);
            $this->entityManager->persist($country);
            $countries[$key] = $country;

            return $country;
        }

        $this->applyCountryFlag($country, $flag);

        return $country;
    }

    private function applyCountryFlag(Country $country, ?string $flagUrl): bool
    {
        if (null === $flagUrl || '' === $flagUrl) {
            return false;
        }

        $previous = $country->getDrapeau();

        if ($this->countryFlagStorage->storeFlagForCountry($country, $flagUrl)) {
            return $previous !== $country->getDrapeau();
        }

        if ($previous !== $flagUrl) {
            $country->setDrapeau($flagUrl);

            return true;
        }

        return false;
    }

    private function applyButeurPhoto(Buteur $buteur, ?string $photoUrl): bool
    {
        if (null === $photoUrl || '' === $photoUrl) {
            return false;
        }

        $previous = $buteur->getPhoto();

        if ($this->buteurPhotoStorage->storePhotoForButeur($buteur, $photoUrl)) {
            return $previous !== $buteur->getPhoto();
        }

        if ($previous !== $photoUrl) {
            $buteur->setPhoto($photoUrl);

            return true;
        }

        return false;
    }

    private function normalizeNameKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array<string, Buteur>
     */
    private function indexButeursByIdentity(): array
    {
        $indexed = [];
        foreach ($this->buteurRepository->findAll() as $buteur) {
            $country = $buteur->getPays();
            if (!$country instanceof Country) {
                continue;
            }

            $indexed[$this->buildButeurIdentityKey((string) $buteur->getPrenom(), (string) $buteur->getNom(), $country)] = $buteur;
        }

        return $indexed;
    }

    /**
     * @param array<string, mixed> $apiPlayer
     */
    private function extractPlayerFirstName(array $apiPlayer): ?string
    {
        $value = $this->normalizeNullableString($apiPlayer['first_name'] ?? $apiPlayer['firstname'] ?? null);
        if (null !== $value) {
            return $value;
        }

        $fullName = $this->normalizeNullableString($apiPlayer['name'] ?? $apiPlayer['full_name'] ?? null);
        if (null === $fullName) {
            return null;
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];

        return isset($parts[0]) ? trim((string) $parts[0]) : null;
    }

    /**
     * @param array<string, mixed> $apiPlayer
     */
    private function extractPlayerLastName(array $apiPlayer): ?string
    {
        $value = $this->normalizeNullableString($apiPlayer['last_name'] ?? $apiPlayer['lastname'] ?? null);
        if (null !== $value) {
            return $value;
        }

        $fullName = $this->normalizeNullableString($apiPlayer['name'] ?? $apiPlayer['full_name'] ?? null);
        if (null === $fullName) {
            return null;
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        if (count($parts) < 2) {
            return null;
        }

        array_shift($parts);

        return trim(implode(' ', $parts));
    }

    /**
     * @param array<string, mixed> $apiPlayer
     */
    private function extractPlayerCountryName(array $apiPlayer): ?string
    {
        if (isset($apiPlayer['team_name']) && \is_string($apiPlayer['team_name'])) {
            return $this->normalizeNullableString($apiPlayer['team_name']);
        }

        if (isset($apiPlayer['country']) && \is_string($apiPlayer['country'])) {
            return $this->normalizeNullableString($apiPlayer['country']);
        }

        if (isset($apiPlayer['team']) && \is_string($apiPlayer['team'])) {
            return $this->normalizeNullableString($apiPlayer['team']);
        }

        return null;
    }

    private function buildButeurIdentityKey(string $prenom, string $nom, Country $country): string
    {
        return sprintf(
            '%s|%s|%s',
            mb_strtolower(trim($prenom)),
            mb_strtolower(trim($nom)),
            $this->normalizeNameKey((string) $country->getNom())
        );
    }
}
