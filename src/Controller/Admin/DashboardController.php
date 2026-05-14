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

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    private const ADMIN_SYNC_PLAYERS_PER_TEAM = 11;

    public function __construct(
        private readonly int $apiFootballSyncMaxRequests,
    ) {
    }

    public function index(): Response
    {
        return $this->redirectToRoute('admin_team_index');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('LPF 2026');
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addJsFile('admin-image-preview.js');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToRoute('Interface joueurs (front)', 'fa fa-globe', 'app_homepage');

        yield MenuItem::section('Gestion');
        yield MenuItem::linkToRoute('Equipes', 'fas fa-people-group', 'admin_team_index');
        yield MenuItem::linkToRoute('Utilisateurs', 'fas fa-users', 'admin_user_index');
        yield MenuItem::linkToRoute('Invitations', 'fas fa-envelope', 'admin_team_invitation_index');

        yield MenuItem::section('Compétition');
        yield MenuItem::linkToRoute('Pays', 'fas fa-flag', 'admin_country_index');
        yield MenuItem::linkToRoute('Buteurs', 'fas fa-user', 'admin_buteur_index');
        yield MenuItem::linkToRoute('Buts', 'fas fa-futbol', 'admin_but_index');
        yield MenuItem::linkToRoute('Matchs', 'fas fa-futbol', 'admin_game_match_index');
        yield MenuItem::linkToRoute('Pronostics', 'fas fa-list-check', 'admin_pronostic_index');
        yield MenuItem::linkToRoute('Classements equipes', 'fas fa-ranking-star', 'admin_team_ranking_snapshot_index');

        yield MenuItem::section('Synchro API-Football');
        yield MenuItem::linkToRoute('Sync pays', 'fas fa-flag', 'admin_wc2026_sync_countries');
        yield MenuItem::linkToRoute('Sync matchs', 'fas fa-calendar-days', 'admin_wc2026_sync_matches');
        yield MenuItem::linkToRoute('Sync joueurs (11 par pays)', 'fas fa-user-plus', 'admin_wc2026_sync_players');
        yield MenuItem::linkToRoute('Sync joueurs (tous, par pays)', 'fas fa-users', 'admin_wc2026_sync_players_country_form');
        yield MenuItem::linkToRoute('Sync tout (pays, matchs, joueurs, buts)', 'fas fa-rotate', 'admin_wc2026_sync');
        yield MenuItem::linkToRoute('Sync buts (événements)', 'fas fa-bullseye', 'admin_wc2026_sync_goals');
        yield MenuItem::linkToRoute('Arrêter synchro joueurs', 'fas fa-stop', 'admin_wc2026_sync_players_stop');
    }

    #[Route('/admin/sync/wc2026/countries', name: 'admin_wc2026_sync_countries', methods: ['GET'])]
    public function syncWc2026Countries(Wc2026SyncService $syncService): Response
    {
        try {
            if (\function_exists('set_time_limit')) {
                @set_time_limit(300);
            }

            $countries = $syncService->syncCountries(500);
            $this->addFlash('success', sprintf(
                'Pays synchronisés : +%d créés, %d mis à jour.',
                $countries['created'],
                $countries['updated']
            ));
        } catch (\Throwable $e) {
            $this->addFlash('danger', sprintf('Erreur synchronisation pays : %s', $e->getMessage()));
        }

        return $this->redirectToRoute('admin');
    }

    #[Route('/admin/sync/wc2026/matches', name: 'admin_wc2026_sync_matches', methods: ['GET'])]
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
                'Synchronisation API-Football terminée. Pays: +%d / maj %d | Matchs: +%d / maj %d / ignorés %d | Joueurs: +%d / maj %d / ignorés %d | Buts: +%d (appels API événements: %d).',
                $countries['created'],
                $countries['updated'],
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
    public function syncWc2026Players(Wc2026SyncService $syncService): Response
    {
        try {
            if (\function_exists('set_time_limit')) {
                @set_time_limit(900);
            }

            $players = $syncService->syncButeurs($this->apiFootballSyncMaxRequests, self::ADMIN_SYNC_PLAYERS_PER_TEAM);

            $msg = sprintf(
                'Synchronisation joueurs terminée (max %d par pays). Joueurs: +%d / maj %d / ignorés %d.',
                self::ADMIN_SYNC_PLAYERS_PER_TEAM,
                $players['created'],
                $players['updated'],
                $players['skipped']
            );
            if (!empty($players['cancelled'])) {
                $this->addFlash('warning', $msg.' Interruption demandée : seules les équipes déjà traitées sont en base.');
            } else {
                $this->addFlash('success', $msg);
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('admin');
    }

    #[Route('/admin/sync/wc2026/players/by-country', name: 'admin_wc2026_sync_players_country_form', methods: ['GET'])]
    public function syncWc2026PlayersCountryForm(CountryRepository $countryRepository): Response
    {
        return $this->render('admin/sync_players_by_country.html.twig', [
            'countries' => $countryRepository->findAllOrderedByName(),
        ]);
    }

    #[Route('/admin/sync/wc2026/players/by-country', name: 'admin_wc2026_sync_players_country_submit', methods: ['POST'])]
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
                'Synchro joueurs pays terminée (effectif complet API). +%d créés, %d mis à jour, %d ignorés.',
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

    #[Route('/admin/sync/wc2026/players/stop', name: 'admin_wc2026_sync_players_stop', methods: ['GET'])]
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
