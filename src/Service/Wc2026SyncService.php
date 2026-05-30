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
        private readonly ApiFootballPlayerBatchSyncState $playerBatchSyncState,
        private readonly ApiFootballPlayerProfileEnrichBatchSyncState $playerProfileEnrichBatchSyncState,
        private readonly ButeurRepository $buteurRepository,
        private readonly ButRepository $butRepository,
        private readonly CountryRepository $countryRepository,
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PronosticScoringService $pronosticScoringService,
        private readonly ButeurGoalScoringService $buteurGoalScoringService,
        private readonly ButeurGoalNotificationService $buteurGoalNotificationService,
        private readonly TeamRankingService $teamRankingService,
        private readonly CountryFlagStorage $countryFlagStorage,
        private readonly ButeurPhotoStorage $buteurPhotoStorage,
        private readonly int $apiFootballWorldCupLeagueId = 1,
        private readonly int $apiFootballWorldCupSeason = 2026,
        /** Nombre max de requêtes /fixtures/events pour une synchro buts (0 = désactivé). */
        private readonly int $apiFootballSyncGoalsMaxRequests = 300,
        /** Appels API max par lot d'enrichissement profils (évite les timeouts HTTP). */
        private readonly int $apiFootballPlayersProfileEnrichMaxCallsPerBatch = 30,
        /** Effectif max importé par sélection (CDM : 26 joueurs). */
        private readonly int $apiFootballSquadMaxPlayers = 26,
    ) {
    }

    /** @var array<string, list<string>> clé pays normalisée => noms équipe API équivalents */
    private const API_TEAM_COUNTRY_ALIASES = [
        'mexique' => ['mexico'],
        'mexico' => ['mexique'],
        'etats unis' => ['united states', 'usa'],
        'états-unis' => ['united states', 'usa'],
        'usa' => ['united states', 'etats unis'],
        'united states' => ['usa', 'etats unis'],
        'coree du sud' => ['south korea'],
        'corée du sud' => ['south korea'],
        'south korea' => ['coree du sud', 'corée du sud'],
        'republique tcheque' => ['czechia'],
        'république tchèque' => ['czechia'],
        'czechia' => ['republique tcheque', 'république tchèque'],
    ];

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
     * Synchronise les sélections compétition de toutes les équipes (endpoint API /players/squads).
     *
     * @param int|null $maxPlayersPerTeam plafond optionnel par pays (null = effectif complet de la sélection)
     *
     * @return array{created:int, updated:int, skipped:int, cancelled:bool}
     */
    public function syncButeurs(int $limit = 1000, ?int $maxPlayersPerTeam = null): array
    {
        $this->assertApiFootballConfigured();

        $this->apiFootballPlayerSyncStop->clear();

        $maxRequests = max(5, min($limit, 5000));
        $result = $this->apiFootballClient->fetchCompetitionSquadPlayersForLeague(
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
     * État de la synchro joueurs par lots (toutes les sélections).
     *
     * @return array{
     *     active: bool,
     *     teams_total: int,
     *     teams_done: int,
     *     teams_remaining: int,
     *     completed: bool,
     *     totals: array{created: int, updated: int, skipped: int},
     *     next_team_names: list<string>
     * }
     */
    public function getButeursBatchSyncStatus(int $previewCount = 5): array
    {
        $state = $this->playerBatchSyncState->load();
        if (null === $state) {
            return [
                'active' => false,
                'teams_total' => 0,
                'teams_done' => 0,
                'teams_remaining' => 0,
                'completed' => false,
                'totals' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
                'next_team_names' => [],
            ];
        }

        $teamsTotal = \count($state['teams']);
        $teamsDone = min($state['next_index'], $teamsTotal);
        $remaining = max(0, $teamsTotal - $teamsDone);
        $nextNames = [];
        for ($i = $state['next_index']; $i < min($teamsTotal, $state['next_index'] + max(1, $previewCount)); ++$i) {
            $nextNames[] = $state['teams'][$i]['name'];
        }

        return [
            'active' => true,
            'teams_total' => $teamsTotal,
            'teams_done' => $teamsDone,
            'teams_remaining' => $remaining,
            'completed' => $state['completed'],
            'totals' => $state['totals'],
            'next_team_names' => $nextNames,
        ];
    }

    public function resetButeursBatchSync(): void
    {
        $this->playerBatchSyncState->reset();
        $this->apiFootballPlayerSyncStop->clear();
    }

    /**
     * État de l'enrichissement profils (prénoms complets) par lots de pays.
     *
     * @return array{
     *     active: bool,
     *     countries_total: int,
     *     countries_done: int,
     *     countries_remaining: int,
     *     completed: bool,
     *     totals: array{updated: int, skipped: int, errors: int, api_calls: int},
     *     next_country_names: list<string>,
     *     countries_done_names: list<string>,
     *     countries_pending_names: list<string>
     * }
     */
    public function getButeursProfileEnrichBatchStatus(int $previewCount = 5): array
    {
        $state = $this->playerProfileEnrichBatchSyncState->load();
        if (null === $state) {
            return [
                'active' => false,
                'countries_total' => 0,
                'countries_done' => 0,
                'countries_remaining' => 0,
                'completed' => false,
                'totals' => ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'api_calls' => 0],
                'next_country_names' => [],
                'countries_done_names' => [],
                'countries_pending_names' => [],
            ];
        }

        $total = \count($state['countries']);
        $done = min($state['next_country_index'], $total);
        $nextNames = [];
        for ($i = $state['next_country_index']; $i < min($total, $state['next_country_index'] + max(1, $previewCount)); ++$i) {
            $nextNames[] = $state['countries'][$i]['name'];
        }

        $doneNames = [];
        for ($i = 0; $i < $done; ++$i) {
            $doneNames[] = $state['countries'][$i]['name'];
        }

        $pendingNames = [];
        for ($i = $done; $i < $total; ++$i) {
            $pendingNames[] = $state['countries'][$i]['name'];
        }

        return [
            'active' => true,
            'countries_total' => $total,
            'countries_done' => $done,
            'countries_remaining' => max(0, $total - $done),
            'completed' => $state['completed'],
            'totals' => $state['totals'],
            'next_country_names' => $nextNames,
            'countries_done_names' => $doneNames,
            'countries_pending_names' => $pendingNames,
        ];
    }

    /**
     * Vue par pays (base de données) : profils complets vs prénoms encore à enrichir.
     *
     * @return array{
     *     synced: list<array{id: int, name: string, players_total: int, players_to_enrich: int}>,
     *     pending: list<array{id: int, name: string, players_total: int, players_to_enrich: int}>
     * }
     */
    public function getProfileEnrichCountriesOverview(): array
    {
        $idsWithApiPlayers = array_flip($this->buteurRepository->findCountryIdsWithApiPlayers());
        $synced = [];
        $pending = [];

        foreach ($this->countryRepository->findAllOrderedByName() as $country) {
            $id = $country->getId();
            $nom = $country->getNom();
            if (null === $id || null === $nom || '' === trim($nom) || !isset($idsWithApiPlayers[$id])) {
                continue;
            }

            $players = $this->buteurRepository->findByCountryWithApiPlayerId((int) $id);
            $toEnrich = 0;
            foreach ($players as $buteur) {
                if ($this->needsProfileEnrichment($buteur)) {
                    ++$toEnrich;
                }
            }

            $entry = [
                'id' => (int) $id,
                'name' => trim($nom),
                'players_total' => \count($players),
                'players_to_enrich' => $toEnrich,
            ];

            if (0 === $toEnrich) {
                $synced[] = $entry;
            } else {
                $pending[] = $entry;
            }
        }

        return ['synced' => $synced, 'pending' => $pending];
    }

    public function resetButeursProfileEnrichBatch(): void
    {
        $this->playerProfileEnrichBatchSyncState->reset();
        $this->apiFootballPlayerSyncStop->clear();
    }

    /**
     * Enrichit les profils joueurs (prénom / nom via /players?id=) par lots de pays.
     *
     * @return array{
     *     updated: int,
     *     skipped: int,
     *     errors: int,
     *     api_calls: int,
     *     cancelled: bool,
     *     batch_countries: int,
     *     countries_total: int,
     *     countries_done: int,
     *     countries_remaining: int,
     *     completed: bool,
     *     processed_country_names: list<string>,
     *     totals: array{updated: int, skipped: int, errors: int, api_calls: int},
     *     hit_call_limit: bool
     * }
     */
    public function enrichButeursProfilesBatch(int $countriesPerBatch): array
    {
        $this->assertApiFootballConfigured();

        $countriesPerBatch = max(1, min($countriesPerBatch, 20));
        $maxCalls = max(5, $this->apiFootballPlayersProfileEnrichMaxCallsPerBatch);
        $state = $this->playerProfileEnrichBatchSyncState->load();

        if (null === $state) {
            $countries = $this->buildProfileEnrichCountryList();

            if ([] === $countries) {
                throw new \RuntimeException(
                    'Aucun pays avec joueurs liés à l’API-Sports. Lancez d’abord la synchro des effectifs.',
                );
            }

            $state = [
                'season' => $this->apiFootballWorldCupSeason,
                'countries' => $countries,
                'next_country_index' => 0,
                'completed' => false,
                'totals' => ['updated' => 0, 'skipped' => 0, 'errors' => 0, 'api_calls' => 0],
            ];
            $this->playerProfileEnrichBatchSyncState->save(
                $state['season'],
                $state['countries'],
                0,
                false,
                $state['totals'],
            );
        }

        $countries = $state['countries'];
        $countriesTotal = \count($countries);
        $startIndex = $state['next_country_index'];

        if ($state['completed'] || $startIndex >= $countriesTotal) {
            return [
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
                'api_calls' => 0,
                'cancelled' => false,
                'batch_countries' => 0,
                'countries_total' => $countriesTotal,
                'countries_done' => $countriesTotal,
                'countries_remaining' => 0,
                'completed' => true,
                'processed_country_names' => [],
                'totals' => $state['totals'],
                'hit_call_limit' => false,
            ];
        }

        $batchUpdated = 0;
        $batchSkipped = 0;
        $batchErrors = 0;
        $batchApiCalls = 0;
        $cancelled = false;
        $hitCallLimit = false;
        $processedNames = [];
        $countriesProcessedInBatch = 0;
        $countryIndex = $startIndex;

        while ($countryIndex < $countriesTotal && $countriesProcessedInBatch < $countriesPerBatch) {
            if ($this->apiFootballPlayerSyncStop->isStopRequested()) {
                $cancelled = true;
                break;
            }

            $countryRef = $countries[$countryIndex];
            $countryDone = $this->enrichPlayersForCountry(
                $countryRef['id'],
                $state['season'],
                $maxCalls - $batchApiCalls,
                $batchUpdated,
                $batchSkipped,
                $batchErrors,
                $batchApiCalls,
                $cancelled,
                $hitCallLimit,
            );

            if ($cancelled) {
                break;
            }

            if ($countryDone) {
                $processedNames[] = $countryRef['name'];
                ++$countriesProcessedInBatch;
                ++$countryIndex;
            }

            if ($hitCallLimit) {
                break;
            }
        }

        if ($batchApiCalls > 0) {
            $this->entityManager->flush();
        }

        $completed = !$cancelled && !$hitCallLimit && $countryIndex >= $countriesTotal;
        $totals = [
            'updated' => $state['totals']['updated'] + $batchUpdated,
            'skipped' => $state['totals']['skipped'] + $batchSkipped,
            'errors' => $state['totals']['errors'] + $batchErrors,
            'api_calls' => $state['totals']['api_calls'] + $batchApiCalls,
        ];

        $this->playerProfileEnrichBatchSyncState->save(
            $state['season'],
            $countries,
            $countryIndex,
            $completed,
            $totals,
        );

        if ($cancelled) {
            $this->apiFootballPlayerSyncStop->clear();
        }

        return [
            'updated' => $batchUpdated,
            'skipped' => $batchSkipped,
            'errors' => $batchErrors,
            'api_calls' => $batchApiCalls,
            'cancelled' => $cancelled,
            'batch_countries' => \count($processedNames),
            'countries_total' => $countriesTotal,
            'countries_done' => $countryIndex,
            'countries_remaining' => max(0, $countriesTotal - $countryIndex),
            'completed' => $completed,
            'processed_country_names' => $processedNames,
            'totals' => $totals,
            'hit_call_limit' => $hitCallLimit,
        ];
    }

    /**
     * @param-out int $updated
     * @param-out int $skipped
     * @param-out int $errors
     * @param-out int $apiCalls
     * @param-out bool $cancelled
     * @param-out bool $hitCallLimit
     */
    private function enrichPlayersForCountry(
        int $countryId,
        int $season,
        int $remainingCalls,
        int &$updated,
        int &$skipped,
        int &$errors,
        int &$apiCalls,
        bool &$cancelled,
        bool &$hitCallLimit,
    ): bool {
        $players = $this->buteurRepository->findByCountryWithApiPlayerId($countryId);
        $needsAny = false;

        foreach ($players as $buteur) {
            if ($this->needsProfileEnrichment($buteur)) {
                $needsAny = true;
                break;
            }
        }

        if (!$needsAny) {
            foreach ($players as $buteur) {
                ++$skipped;
            }

            return true;
        }

        foreach ($players as $buteur) {
            if ($this->apiFootballPlayerSyncStop->isStopRequested()) {
                $cancelled = true;

                return false;
            }

            if ($apiCalls >= $remainingCalls) {
                $hitCallLimit = true;

                return false;
            }

            if (!$this->needsProfileEnrichment($buteur)) {
                ++$skipped;
                continue;
            }

            $apiPlayerId = $buteur->getApiSportsPlayerId();
            if (null === $apiPlayerId) {
                ++$skipped;
                continue;
            }

            try {
                $profile = $this->apiFootballClient->fetchPlayerProfileById($apiPlayerId, $season);
                ++$apiCalls;

                if (null === $profile) {
                    ++$errors;
                    continue;
                }

                if ($this->applyProfileToButeur($buteur, $profile)) {
                    ++$updated;
                } else {
                    ++$skipped;
                }
            } catch (\Throwable) {
                ++$errors;
            }
        }

        return true;
    }

    /**
     * @param array{firstname: ?string, lastname: ?string, position: ?string, number: ?int} $profile
     */
    private function applyProfileToButeur(Buteur $buteur, array $profile): bool
    {
        $changed = false;
        $firstname = $profile['firstname'] ?? null;
        $lastname = $profile['lastname'] ?? null;

        if ($this->shouldReplaceFirstName((string) $buteur->getPrenom(), $firstname)) {
            $buteur->setPrenom($firstname);
            $changed = true;
        }

        if (null !== $lastname && '' !== $lastname && $buteur->getNom() !== $lastname) {
            $buteur->setNom($lastname);
            $changed = true;
        }

        $position = $profile['position'] ?? null;
        if (null !== $position && (null === $buteur->getPosition() || '' === $buteur->getPosition())) {
            $buteur->setPosition($position);
            $changed = true;
        }

        $number = $profile['number'] ?? null;
        if (null !== $number && null === $buteur->getNumero()) {
            $buteur->setNumero($number);
            $changed = true;
        }

        return $changed;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function buildProfileEnrichCountryList(): array
    {
        $idsWithApiPlayers = array_flip($this->buteurRepository->findCountryIdsWithApiPlayers());
        $countries = [];

        foreach ($this->countryRepository->findAllOrderedByName() as $country) {
            $id = $country->getId();
            $nom = $country->getNom();
            if (null === $id || null === $nom || '' === trim($nom) || !isset($idsWithApiPlayers[$id])) {
                continue;
            }

            $countries[] = ['id' => (int) $id, 'name' => trim($nom)];
        }

        return $countries;
    }

    private function needsProfileEnrichment(Buteur $buteur): bool
    {
        $prenom = trim((string) $buteur->getPrenom());
        if ('' === $prenom || '-' === $prenom) {
            return true;
        }

        return $this->isAbbreviatedFirstName($prenom);
    }

    private function isAbbreviatedFirstName(string $prenom): bool
    {
        if (preg_match('/^[A-Za-zÀ-ÿ]\.?$/u', $prenom)) {
            return true;
        }

        if (str_ends_with($prenom, '.') && mb_strlen($prenom) <= 4) {
            return true;
        }

        return false;
    }

    private function shouldReplaceFirstName(string $current, ?string $apiFirstname): bool
    {
        if (null === $apiFirstname || '' === $apiFirstname) {
            return false;
        }

        if ($current === $apiFirstname) {
            return false;
        }

        if ('' === $current || '-' === $current || $this->isAbbreviatedFirstName($current)) {
            return true;
        }

        return mb_strlen($apiFirstname) > mb_strlen($current);
    }

    /**
     * Synchronise un lot de sélections nationales (reprise automatique entre les lots).
     *
     * @return array{
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     cancelled: bool,
     *     batch_teams: int,
     *     teams_total: int,
     *     teams_done: int,
     *     teams_remaining: int,
     *     completed: bool,
     *     processed_team_names: list<string>,
     *     totals: array{created: int, updated: int, skipped: int}
     * }
     */
    public function syncButeursBatch(int $batchSize): array
    {
        $this->assertApiFootballConfigured();

        $batchSize = max(1, min($batchSize, 30));
        $state = $this->playerBatchSyncState->load();

        if (null === $state) {
            $teams = $this->apiFootballClient->fetchCompetitionTeamsForLeague(
                $this->apiFootballWorldCupLeagueId,
                $this->apiFootballWorldCupSeason,
            );
            if ([] === $teams) {
                throw new \RuntimeException('Aucune équipe trouvée pour cette compétition (vérifiez la clé API et la saison).');
            }

            $state = [
                'league_id' => $this->apiFootballWorldCupLeagueId,
                'season' => $this->apiFootballWorldCupSeason,
                'teams' => $teams,
                'next_index' => 0,
                'completed' => false,
                'totals' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
            ];
            $this->playerBatchSyncState->save(
                $state['league_id'],
                $state['season'],
                $state['teams'],
                0,
                false,
                $state['totals'],
            );
        }

        $teams = $state['teams'];
        $teamsTotal = \count($teams);
        $startIndex = $state['next_index'];

        if ($state['completed'] || $startIndex >= $teamsTotal) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'cancelled' => false,
                'batch_teams' => 0,
                'teams_total' => $teamsTotal,
                'teams_done' => $teamsTotal,
                'teams_remaining' => 0,
                'completed' => true,
                'processed_team_names' => [],
                'totals' => $state['totals'],
            ];
        }

        $cancelled = false;
        $processedNames = [];
        $import = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $endIndex = min($startIndex + $batchSize, $teamsTotal);

        for ($i = $startIndex; $i < $endIndex; ++$i) {
            if ($this->apiFootballPlayerSyncStop->isStopRequested()) {
                $cancelled = true;
                break;
            }

            $team = $teams[$i];
            $chunk = $this->apiFootballClient->fetchCompetitionSquadPlayersForTeam(
                $team['id'],
                $team['name'],
                $this->apiFootballSquadMaxPlayers,
            );

            if ($chunk['cancelled']) {
                $cancelled = true;
                break;
            }

            $teamImport = $this->importButeursFromNormalizedList($chunk['rows']);
            $import['created'] += $teamImport['created'];
            $import['updated'] += $teamImport['updated'];
            $import['skipped'] += $teamImport['skipped'];
            $this->applySquadMembershipForTeamName($team['name'], $chunk['rows']);
            $processedNames[] = $team['name'];
        }

        if ([] !== $processedNames) {
            $this->entityManager->flush();
        }

        $newIndex = $startIndex + \count($processedNames);
        $completed = !$cancelled && $newIndex >= $teamsTotal;
        $totals = [
            'created' => $state['totals']['created'] + $import['created'],
            'updated' => $state['totals']['updated'] + $import['updated'],
            'skipped' => $state['totals']['skipped'] + $import['skipped'],
        ];

        $this->playerBatchSyncState->save(
            $state['league_id'],
            $state['season'],
            $teams,
            $newIndex,
            $completed,
            $totals,
        );

        if ($cancelled) {
            $this->apiFootballPlayerSyncStop->clear();
        }

        return [
            'created' => $import['created'],
            'updated' => $import['updated'],
            'skipped' => $import['skipped'],
            'cancelled' => $cancelled,
            'batch_teams' => \count($processedNames),
            'teams_total' => $teamsTotal,
            'teams_done' => $newIndex,
            'teams_remaining' => max(0, $teamsTotal - $newIndex),
            'completed' => $completed,
            'processed_team_names' => $processedNames,
            'totals' => $totals,
        ];
    }

    /**
     * Synchronise les joueurs d’un seul pays (équipe CDM) via la sélection compétition
     * (endpoint API /players/squads). Le nom du pays en base doit correspondre à une
     * équipe /teams de la ligue (ex. après sync pays).
     *
     * @param int|null $maxPlayersPerTeam paramètre conservé pour compatibilité (ignoré en mode sélection compétition)
     *
     * @return array{created:int, updated:int, skipped:int, cancelled:bool, squad_size:int, deactivated:int}
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

            if (!$this->countryMatchesApiTeamName((string) $country->getNom(), $name)) {
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

        $maxPlayers = (null !== $maxPlayersPerTeam && $maxPlayersPerTeam > 0)
            ? $maxPlayersPerTeam
            : $this->apiFootballSquadMaxPlayers;

        $result = $this->apiFootballClient->fetchCompetitionSquadPlayersForTeam(
            $teamId,
            $teamName,
            $maxPlayers,
        );

        $import = $this->importButeursFromNormalizedList($result['rows']);
        $deactivated = $this->applySquadMembershipForCountry($country, $result['rows']);

        if ($result['cancelled']) {
            $this->apiFootballPlayerSyncStop->clear();
        }

        $this->entityManager->flush();

        return array_merge($import, [
            'cancelled' => $result['cancelled'],
            'squad_size' => \count($result['rows']),
            'deactivated' => $deactivated,
        ]);
    }

    /**
     * Met à jour un match depuis GET /fixtures?id=… (score, statut, minute).
     *
     * @return array{updated:bool, score_changed:bool, status_changed:bool, old_status:string, new_status:string}
     */
    public function syncMatchFromApi(GameMatch $match): array
    {
        $this->assertApiFootballConfigured();

        $fixtureId = $match->getApiFootballFixtureId();
        if (null === $fixtureId) {
            throw new \InvalidArgumentException('Ce match n’a pas d’identifiant API-Football (fixture).');
        }

        $oldStatus = $match->getStatut();
        $oldScoreDomicile = $match->getScoreDomicile();
        $oldScoreExterieur = $match->getScoreExterieur();

        $row = $this->apiFootballClient->fetchFixtureById($fixtureId);
        if (null === $row) {
            throw new \RuntimeException(sprintf('Fixture API %d introuvable.', $fixtureId));
        }

        $parsed = $this->parseApiFootballFixtureRow($row);
        if (null === $parsed) {
            throw new \RuntimeException(sprintf('Réponse API invalide pour la fixture %d.', $fixtureId));
        }

        $match
            ->setStatut($this->mapApiFootballFixtureStatus($parsed['status_short']))
            ->setScoreDomicile($parsed['score_home'])
            ->setScoreExterieur($parsed['score_away'])
            ->setLiveElapsedMinute($parsed['elapsed'])
            ->setApiFootballLastSyncedAt(new \DateTimeImmutable());

        if (null !== $parsed['venue_name']) {
            $match->setVenueName($parsed['venue_name']);
        }

        if (null !== $parsed['referee']) {
            $match->setReferee($parsed['referee']);
        }

        $scoreChanged = ($oldScoreDomicile !== $match->getScoreDomicile())
            || ($oldScoreExterieur !== $match->getScoreExterieur());
        $statusChanged = $oldStatus !== $match->getStatut();

        if ($scoreChanged) {
            $this->pronosticScoringService->rescoreForMatch($match);
        }

        return [
            'updated' => $scoreChanged || $statusChanged,
            'score_changed' => $scoreChanged,
            'status_changed' => $statusChanged,
            'old_status' => $oldStatus,
            'new_status' => $match->getStatut(),
        ];
    }

    /**
     * Importe les buts d’un seul match (événements Goal).
     *
     * @return array{created:int, skipped:int}
     */
    public function syncGoalsForMatch(GameMatch $match): array
    {
        $this->assertApiFootballConfigured();

        $fixtureId = $match->getApiFootballFixtureId();
        if (null === $fixtureId) {
            return ['created' => 0, 'skipped' => 0];
        }

        $events = $this->apiFootballClient->fetchFixtureEvents($fixtureId);

        return $this->importGoalEventsForMatch($match, $fixtureId, $events);
    }

    /**
     * Recalcule pronostics, points buteurs et classement équipes après la fin du match.
     */
    public function finalizeMatchAfterFullTime(GameMatch $match): void
    {
        if (null !== $match->getLiveScoresFinalizedAt()) {
            return;
        }

        if ('FINISHED' !== $match->getStatut() && 'CANCELLED' !== $match->getStatut()) {
            return;
        }

        if (null !== $match->getScoreDomicile() && null !== $match->getScoreExterieur()) {
            $this->pronosticScoringService->rescoreForMatch($match);
        }

        $this->buteurGoalScoringService->rescoreAll();
        $this->teamRankingService->rebuildSnapshotsFromMatch($match);
        $match->setLiveElapsedMinute(null);
        $match->setLiveScoresFinalizedAt(new \DateTimeImmutable());
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

            if (!$match->isApiFootballSyncEnabled()) {
                continue;
            }

            $events = $this->apiFootballClient->fetchFixtureEvents($fixtureId);
            ++$apiCalls;

            $import = $this->importGoalEventsForMatch($match, $fixtureId, $events);
            $created += $import['created'];
            $skipped += $import['skipped'];
        }

        $this->entityManager->flush();

        if ($created > 0) {
            $this->buteurGoalScoringService->rescoreAll();
            $latest = $this->gameMatchRepository->findLatestFinishedMatch();
            if ($latest instanceof GameMatch) {
                $this->teamRankingService->rebuildSnapshotsFromMatch($latest);
            }
        }

        return ['created' => $created, 'skipped' => $skipped, 'api_calls' => $apiCalls];
    }

    /**
     * @param list<array<string, mixed>> $events
     *
     * @return array{created:int, skipped:int}
     */
    private function importGoalEventsForMatch(GameMatch $match, int $fixtureId, array $events): array
    {
        $created = 0;
        $skipped = 0;
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

            $minute = $elapsed > 0 ? $elapsed : null;
            if ($extra > 0 && null !== $minute) {
                $minute = $minute + $extra;
            }

            $but = (new But())
                ->setButeur($buteur)
                ->setMatchRef($match)
                ->setMinute($minute)
                ->setApiSportsEventKey($eventKey);
            $this->buteurGoalScoringService->scoreBut($but);
            $this->entityManager->persist($but);
            $this->buteurGoalNotificationService->notifyForNewBut($but);
            ++$created;
        }

        if ($created > 0) {
            $this->buteurGoalScoringService->rescoreAll();
        }

        return ['created' => $created, 'skipped' => $skipped];
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
     *     elapsed: ?int,
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
        $elapsed = null;
        if (\is_array($status) && isset($status['elapsed']) && is_numeric($status['elapsed'])) {
            $elapsed = (int) $status['elapsed'];
        }

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
            'elapsed' => $elapsed,
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
            $position = $this->normalizeNullableString($apiPlayer['position'] ?? null);
            $numero = isset($apiPlayer['number']) && is_numeric($apiPlayer['number'])
                ? (int) $apiPlayer['number']
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
                    ->setPays($country)
                    ->setPosition($position)
                    ->setNumero($numero);
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
            if (null !== $position && $buteur->getPosition() !== $position) {
                $buteur->setPosition($position);
                $changed = true;
            }
            if (null !== $numero && $buteur->getNumero() !== $numero) {
                $buteur->setNumero($numero);
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
    private function findCountryByName(array $countries, string $name): ?Country
    {
        $key = $this->normalizeNameKey($name);
        if ('' === $key) {
            return null;
        }

        $country = $countries[$key] ?? null;
        if ($country instanceof Country) {
            return $country;
        }

        foreach ($countries as $country) {
            if ($this->countryMatchesApiTeamName((string) $country->getNom(), $name)) {
                return $country;
            }
        }

        return null;
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

    private function countryMatchesApiTeamName(string $countryName, string $apiTeamName): bool
    {
        $countryKey = $this->normalizeNameKey($countryName);
        $apiKey = $this->normalizeNameKey($apiTeamName);

        if ($countryKey === $apiKey) {
            return true;
        }

        return \in_array($apiKey, self::API_TEAM_COUNTRY_ALIASES[$countryKey] ?? [], true)
            || \in_array($countryKey, self::API_TEAM_COUNTRY_ALIASES[$apiKey] ?? [], true);
    }

    /**
     * @param list<array<string, mixed>> $squadRows
     */
    private function applySquadMembershipForTeamName(string $teamName, array $squadRows): int
    {
        $countries = $this->indexCountriesByName();
        $country = $this->findCountryByName($countries, $teamName);
        if (!$country instanceof Country) {
            return 0;
        }

        return $this->applySquadMembershipForCountry($country, $squadRows);
    }

    /**
     * Active uniquement les joueurs présents dans la sélection importée (les autres passent inactifs).
     *
     * @param list<array<string, mixed>> $squadRows
     */
    private function applySquadMembershipForCountry(Country $country, array $squadRows): int
    {
        $countryId = $country->getId();
        if (null === $countryId) {
            return 0;
        }

        $activeApiIds = [];
        foreach ($squadRows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $apiId = $row['api_sports_player_id'] ?? null;
            if (is_numeric($apiId)) {
                $activeApiIds[(int) $apiId] = true;
            }
        }

        if ([] === $activeApiIds) {
            return 0;
        }

        $deactivated = 0;
        foreach ($this->buteurRepository->findAllByCountryId($countryId) as $buteur) {
            $apiId = $buteur->getApiSportsPlayerId();
            if (null === $apiId) {
                continue;
            }

            $shouldBeActive = isset($activeApiIds[$apiId]);
            if ($buteur->isActif() === $shouldBeActive) {
                continue;
            }

            $buteur->setActif($shouldBeActive);
            if (!$shouldBeActive) {
                ++$deactivated;
            }
        }

        return $deactivated;
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
