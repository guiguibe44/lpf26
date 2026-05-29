<?php

namespace App\Controller\Admin;

use App\Entity\Team;
use App\Entity\TeamInvitation;
use App\Entity\TeamMember;
use App\Entity\Pronostic;
use App\Entity\TeamRankingSnapshot;
use App\Repository\CountryRepository;
use App\Service\ApiFootballPlayerSyncStop;
use App\Service\Wc2026SyncService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{

    public function __construct(
        private readonly int $apiFootballSyncMaxRequests,
        private readonly int $apiFootballPlayersSyncBatchSize,
        private readonly int $apiFootballPlayersProfileEnrichMaxCallsPerBatch,
    ) {
    }

    public function index(): Response
    {
        return $this->redirectToRoute('admin_team_index');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('LPF 2026')
            ->setLocales(['fr']);
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addJsFile('admin-image-preview.js');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::linkToRoute('Interface joueurs (front)', 'fa fa-globe', 'app_homepage');

        yield MenuItem::subMenu('Communication', 'fas fa-comments')
            ->setSubItems([
                MenuItem::linkToRoute('Messages forum', 'fas fa-comments', 'admin_forum_post_index'),
                MenuItem::linkToRoute('Messages manuels', 'fas fa-paper-plane', 'admin_manual_messages'),
                MenuItem::linkToRoute('Historique relances prono', 'fas fa-history', 'admin_pronostic_reminders_history'),
            ]);

        yield MenuItem::linkToRoute('Checklist de recette', 'fas fa-list-check', 'admin_qa_checklist');
        yield MenuItem::linkToRoute('Checklist compétition', 'fas fa-trophy', 'admin_compet_checklist');
        yield MenuItem::linkToRoute('Scénario jokers (test)', 'fas fa-mask', 'admin_joker_test_scenario');

        yield MenuItem::subMenu('Gestion', 'fas fa-people-group')
            ->setSubItems([
                MenuItem::linkToRoute('Equipes', 'fas fa-people-group', 'admin_team_index'),
                MenuItem::linkToRoute('Utilisateurs', 'fas fa-users', 'admin_user_index'),
                MenuItem::linkToRoute('Invitations', 'fas fa-envelope', 'admin_team_invitation_index'),
            ]);

        yield MenuItem::subMenu('Apparence', 'fas fa-palette')
            ->setSubItems([
                MenuItem::linkToRoute('Thèmes de page', 'fas fa-brush', 'admin_main_theme_index'),
                MenuItem::linkToRoute('Présentation du site', 'fas fa-play-circle', 'admin_site_intro_slide_index'),
            ]);

        yield MenuItem::subMenu('Compétition', 'fas fa-trophy')
            ->setSubItems([
                MenuItem::linkToRoute('Pays', 'fas fa-flag', 'admin_country_index'),
                MenuItem::linkToRoute('Buteurs', 'fas fa-user', 'admin_buteur_index'),
                MenuItem::linkToRoute('Buts', 'fas fa-futbol', 'admin_but_index'),
                MenuItem::linkToRoute('Matchs', 'fas fa-futbol', 'admin_game_match_index'),
                MenuItem::linkToRoute('Pronostics', 'fas fa-list-check', 'admin_pronostic_index'),
                MenuItem::linkToRoute('Classements equipes', 'fas fa-ranking-star', 'admin_team_ranking_snapshot_index'),
                MenuItem::linkToRoute('Jokers', 'fas fa-wand-magic-sparkles', 'admin_joker_index'),
                MenuItem::linkToRoute('Utilisations jokers', 'fas fa-hat-wizard', 'admin_team_joker_usage_index'),
            ]);

        yield MenuItem::subMenu('Synchro API-Football', 'fas fa-rotate')
            ->setSubItems([
                MenuItem::linkToRoute('Sync pays', 'fas fa-flag', 'admin_wc2026_sync_countries'),
                MenuItem::linkToRoute('Sync matchs', 'fas fa-calendar-days', 'admin_wc2026_sync_matches'),
                MenuItem::linkToRoute('Sync joueurs par pays', 'fas fa-user', 'admin_wc2026_sync_players_country_form'),
                MenuItem::linkToRoute('Sync joueurs (tous)', 'fas fa-users', 'admin_wc2026_sync_players'),
                MenuItem::linkToRoute('Enrichir profils joueurs', 'fas fa-id-card', 'admin_wc2026_sync_players_profile_enrich'),
                MenuItem::linkToRoute('Sync buts', 'fas fa-bullseye', 'admin_wc2026_sync_goals'),
            ]);
    }

    #[Route('/admin/sync/wc2026/repair-group-phases', name: 'admin_wc2026_repair_group_phases', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function repairWc2026GroupPhases(Wc2026SyncService $syncService): Response
    {
        try {
            $result = $syncService->repairGroupMatchPhases();
            $this->addFlash('success', sprintf(
                'Phases de groupes mises à jour sur %d match(s).',
                $result['updated'],
            ));
        } catch (\Throwable $e) {
            $this->addFlash('danger', sprintf('Erreur réparation phases : %s', $e->getMessage()));
        }

        return $this->redirectToRoute('admin');
    }

    #[Route('/admin/sync/wc2026/countries', name: 'admin_wc2026_sync_countries', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function syncWc2026Countries(Wc2026SyncService $syncService): Response
    {
        try {
            if (\function_exists('set_time_limit')) {
                @set_time_limit(300);
            }

            $countries = $syncService->syncCountries(500);
            $this->addFlash('success', sprintf(
                'Pays synchronisés : +%d créés, %d mis à jour, %d drapeaux téléchargés.',
                $countries['created'],
                $countries['updated'],
                $countries['flags_downloaded'],
            ));
        } catch (\Throwable $e) {
            $this->addFlash('danger', sprintf('Erreur synchronisation pays : %s', $e->getMessage()));
        }

        return $this->redirectToRoute('admin');
    }

    #[Route('/admin/sync/wc2026/matches', name: 'admin_wc2026_sync_matches', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function syncWc2026Matches(Wc2026SyncService $syncService): Response
    {
        try {
            if (\function_exists('set_time_limit')) {
                @set_time_limit(600);
            }

            $matches = $syncService->syncMatches(500);
            $this->addFlash('success', sprintf(
                'Matchs synchronisés : +%d créés, %d mis à jour, %d ignorés.',
                $matches['created'],
                $matches['updated'],
                $matches['skipped']
            ));
        } catch (\Throwable $e) {
            $this->addFlash('danger', sprintf('Erreur synchronisation matchs : %s', $e->getMessage()));
        }

        return $this->redirectToRoute('admin');
    }

    #[Route('/admin/sync/wc2026', name: 'admin_wc2026_sync', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function syncWc2026(Wc2026SyncService $syncService): Response
    {
        try {
            if (\function_exists('set_time_limit')) {
                @set_time_limit(900);
            }

            $countries = $syncService->syncCountries(500);
            $matches = $syncService->syncMatches(500);
            $players = $syncService->syncButeurs($this->apiFootballSyncMaxRequests, null);
            $goals = $syncService->syncButsFromFixtureEvents();

            $msg = sprintf(
                'Synchronisation API-Football terminée. Pays: +%d / maj %d / drapeaux %d | Matchs: +%d / maj %d / ignorés %d | Joueurs: +%d / maj %d / ignorés %d | Buts: +%d (appels API événements: %d).',
                $countries['created'],
                $countries['updated'],
                $countries['flags_downloaded'],
                $matches['created'],
                $matches['updated'],
                $matches['skipped'],
                $players['created'],
                $players['updated'],
                $players['skipped'],
                $goals['created'],
                $goals['api_calls']
            );
            if (!empty($players['cancelled'])) {
                $msg .= ' Synchro joueurs interrompue (données partielles enregistrées).';
            }
            $this->addFlash('success', $msg);
        } catch (\Throwable $e) {
            $this->addFlash('danger', sprintf('Erreur synchronisation API: %s', $e->getMessage()));
        }

        return $this->redirectToRoute('admin');
    }

    #[Route('/admin/sync/wc2026/players', name: 'admin_wc2026_sync_players', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function syncWc2026PlayersBatchPage(Wc2026SyncService $syncService): Response
    {
        return $this->render('admin/sync_players_batch.html.twig', [
            'status' => $syncService->getButeursBatchSyncStatus(),
            'batch_size' => $this->apiFootballPlayersSyncBatchSize,
        ]);
    }

    #[Route('/admin/sync/wc2026/players/batch', name: 'admin_wc2026_sync_players_batch_run', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function syncWc2026PlayersBatchRun(Request $request, Wc2026SyncService $syncService): Response
    {
        if (!$this->isCsrfTokenValid('sync_wc2026_players_batch', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide (CSRF).');

            return $this->redirectToRoute('admin_wc2026_sync_players');
        }

        try {
            if (\function_exists('set_time_limit')) {
                @set_time_limit(300);
            }

            $result = $syncService->syncButeursBatch($this->apiFootballPlayersSyncBatchSize);

            if ($result['completed'] && 0 === $result['batch_teams']) {
                $this->addFlash('info', 'Synchro déjà terminée. Cliquez sur « Réinitialiser » pour recommencer.');
            } else {
                $teamsLabel = implode(', ', $result['processed_team_names']);
                $msg = sprintf(
                    'Lot traité (%d équipe(s) : %s). Ce lot : +%d créés, %d maj, %d ignorés. Progression %d/%d.',
                    $result['batch_teams'],
                    $teamsLabel,
                    $result['created'],
                    $result['updated'],
                    $result['skipped'],
                    $result['teams_done'],
                    $result['teams_total'],
                );
                if ($result['completed']) {
                    $msg .= sprintf(
                        ' Synchro terminée (cumul : +%d créés, %d maj, %d ignorés).',
                        $result['totals']['created'],
                        $result['totals']['updated'],
                        $result['totals']['skipped'],
                    );
                    $this->addFlash('success', $msg);
                } elseif ($result['cancelled']) {
                    $this->addFlash('warning', $msg.' Interruption demandée : relancez « Lot suivant » pour reprendre.');
                } else {
                    $this->addFlash('success', $msg);
                }
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_wc2026_sync_players');
    }

    #[Route('/admin/sync/wc2026/players/batch/reset', name: 'admin_wc2026_sync_players_batch_reset', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function syncWc2026PlayersBatchReset(Request $request, Wc2026SyncService $syncService): Response
    {
        if (!$this->isCsrfTokenValid('sync_wc2026_players_batch_reset', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide (CSRF).');

            return $this->redirectToRoute('admin_wc2026_sync_players');
        }

        $syncService->resetButeursBatchSync();
        $this->addFlash('success', 'Progression réinitialisée. Cliquez sur « Démarrer » pour une nouvelle synchro.');

        return $this->redirectToRoute('admin_wc2026_sync_players');
    }

    #[Route('/admin/sync/wc2026/players/profile-enrich', name: 'admin_wc2026_sync_players_profile_enrich', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function syncWc2026PlayersProfileEnrichPage(Wc2026SyncService $syncService): Response
    {
        return $this->render('admin/sync_players_profile_enrich.html.twig', [
            'status' => $syncService->getButeursProfileEnrichBatchStatus(),
            'batch_size' => $this->apiFootballPlayersSyncBatchSize,
            'max_calls_per_batch' => $this->apiFootballPlayersProfileEnrichMaxCallsPerBatch,
        ]);
    }

    #[Route('/admin/sync/wc2026/players/profile-enrich/batch', name: 'admin_wc2026_sync_players_profile_enrich_run', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function syncWc2026PlayersProfileEnrichRun(Request $request, Wc2026SyncService $syncService): Response
    {
        if (!$this->isCsrfTokenValid('sync_wc2026_players_profile_enrich', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide (CSRF).');

            return $this->redirectToRoute('admin_wc2026_sync_players_profile_enrich');
        }

        try {
            if (\function_exists('set_time_limit')) {
                @set_time_limit(600);
            }

            $result = $syncService->enrichButeursProfilesBatch($this->apiFootballPlayersSyncBatchSize);

            if ($result['completed'] && 0 === $result['batch_countries']) {
                $this->addFlash('info', 'Enrichissement déjà terminé. Cliquez sur « Réinitialiser » pour recommencer.');
            } else {
                $countriesLabel = implode(', ', $result['processed_country_names']);
                $msg = sprintf(
                    'Lot traité (%d pays : %s). Ce lot : %d maj, %d ignorés, %d erreurs, %d appels API. Progression %d/%d.',
                    $result['batch_countries'],
                    $countriesLabel,
                    $result['updated'],
                    $result['skipped'],
                    $result['errors'],
                    $result['api_calls'],
                    $result['countries_done'],
                    $result['countries_total'],
                );
                if ($result['hit_call_limit']) {
                    $msg .= ' Limite d’appels API atteinte pour cette requête : relancez « Lot suivant » (les joueurs déjà enrichis seront ignorés).';
                }
                if ($result['completed']) {
                    $msg .= sprintf(
                        ' Terminé (cumul : %d maj, %d ignorés, %d erreurs, %d appels API).',
                        $result['totals']['updated'],
                        $result['totals']['skipped'],
                        $result['totals']['errors'],
                        $result['totals']['api_calls'],
                    );
                    $this->addFlash('success', $msg);
                } elseif ($result['cancelled']) {
                    $this->addFlash('warning', $msg.' Interruption demandée.');
                } else {
                    $this->addFlash('success', $msg);
                }
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin_wc2026_sync_players_profile_enrich');
    }

    #[Route('/admin/sync/wc2026/players/profile-enrich/reset', name: 'admin_wc2026_sync_players_profile_enrich_reset', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function syncWc2026PlayersProfileEnrichReset(Request $request, Wc2026SyncService $syncService): Response
    {
        if (!$this->isCsrfTokenValid('sync_wc2026_players_profile_enrich_reset', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide (CSRF).');

            return $this->redirectToRoute('admin_wc2026_sync_players_profile_enrich');
        }

        $syncService->resetButeursProfileEnrichBatch();
        $this->addFlash('success', 'Progression réinitialisée. Cliquez sur « Démarrer » pour un nouvel enrichissement.');

        return $this->redirectToRoute('admin_wc2026_sync_players_profile_enrich');
    }

    #[Route('/admin/sync/wc2026/players/by-country', name: 'admin_wc2026_sync_players_country_form', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function syncWc2026PlayersCountryForm(CountryRepository $countryRepository): Response
    {
        return $this->render('admin/sync_players_by_country.html.twig', [
            'countries' => $countryRepository->findAllOrderedByName(),
        ]);
    }

    #[Route('/admin/sync/wc2026/players/by-country', name: 'admin_wc2026_sync_players_country_submit', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function syncWc2026PlayersCountrySubmit(Request $request, Wc2026SyncService $syncService): Response
    {
        if (!$this->isCsrfTokenValid('sync_wc2026_players_country', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide (CSRF). Rechargez la page et réessayez.');

            return $this->redirectToRoute('admin_wc2026_sync_players_country_form');
        }

        $rawId = $request->request->get('country');
        $countryId = \is_numeric($rawId) ? (int) $rawId : 0;
        if ($countryId <= 0) {
            $this->addFlash('danger', 'Pays invalide.');

            return $this->redirectToRoute('admin_wc2026_sync_players_country_form');
        }

        try {
            if (\function_exists('set_time_limit')) {
                @set_time_limit(900);
            }

            $players = $syncService->syncButeursForCountry($countryId, $this->apiFootballSyncMaxRequests, null);

            $msg = sprintf(
                'Synchro joueurs pays terminée (sélection compétition API). +%d créés, %d mis à jour, %d ignorés.',
                $players['created'],
                $players['updated'],
                $players['skipped']
            );
            if (!empty($players['cancelled'])) {
                $this->addFlash('warning', $msg.' Interruption demandée : données partielles enregistrées.');
            } else {
                $this->addFlash('success', $msg);
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin');
    }

    #[Route('/admin/sync/wc2026/goals', name: 'admin_wc2026_sync_goals', methods: ['GET'])]
    public function syncWc2026Goals(Wc2026SyncService $syncService): Response
    {
        try {
            if (\function_exists('set_time_limit')) {
                @set_time_limit(900);
            }

            $goals = $syncService->syncButsFromFixtureEvents();
            $this->addFlash('success', sprintf(
                'Buts synchronisés : +%d créés, %d lignes événements ignorées, %d appels API /fixtures/events.',
                $goals['created'],
                $goals['skipped'],
                $goals['api_calls']
            ));
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin');
    }

    #[Route('/admin/sync/download/country-flags', name: 'admin_download_country_flags', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function downloadCountryFlags(Wc2026SyncService $syncService): Response
    {
        try {
            if (\function_exists('set_time_limit')) {
                @set_time_limit(300);
            }

            $result = $syncService->downloadAllCountryFlags();
            $this->addFlash('success', sprintf(
                'Drapeaux : %d téléchargés, %d ignorés (déjà locaux ou absents), %d échecs.',
                $result['downloaded'],
                $result['skipped'],
                $result['failed'],
            ));
        } catch (\Throwable $e) {
            $this->addFlash('danger', sprintf('Erreur téléchargement drapeaux : %s', $e->getMessage()));
        }

        return $this->redirectToRoute('admin');
    }

    #[Route('/admin/sync/download/buteur-photos', name: 'admin_download_buteur_photos', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function downloadButeurPhotos(Wc2026SyncService $syncService): Response
    {
        try {
            if (\function_exists('set_time_limit')) {
                @set_time_limit(600);
            }

            $result = $syncService->downloadAllButeurPhotos();
            $this->addFlash('success', sprintf(
                'Photos joueurs : %d téléchargées, %d ignorées (déjà locales ou absentes), %d échecs.',
                $result['downloaded'],
                $result['skipped'],
                $result['failed'],
            ));
        } catch (\Throwable $e) {
            $this->addFlash('danger', sprintf('Erreur téléchargement photos : %s', $e->getMessage()));
        }

        return $this->redirectToRoute('admin');
    }

    #[Route('/admin/sync/wc2026/players/stop', name: 'admin_wc2026_sync_players_stop', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN', message: 'Synchronisation API réservée aux administrateurs.')]
    public function stopWc2026Players(ApiFootballPlayerSyncStop $playerSyncStop): Response
    {
        $playerSyncStop->requestStop();
        $this->addFlash(
            'warning',
            'Arrêt de la synchro joueurs demandé. Si une synchro est en cours, elle s’interrompra après la requête API en cours (quelques secondes). Ouvrez un autre onglet ou recliquez ici pendant la synchro.'
        );

        return $this->redirectToRoute('admin');
    }
}
