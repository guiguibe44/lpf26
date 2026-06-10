<?php

namespace App\Controller;

use App\Entity\Buteur;
use App\Entity\GameMatch;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\ButeurRepository;
use App\Repository\ButRepository;
use App\Repository\DashboardEditorialRepository;
use App\Repository\GameMatchRepository;
use App\Repository\PronosticRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRankingSnapshotRepository;
use App\Repository\UserRepository;
use App\Service\BadgeFeature;
use App\Service\ButeurGoalScoringService;
use App\Service\CompetitionStatsRandomPicker;
use App\Service\PreCompetitionDashboardService;
use App\Service\DefaultPronosticService;
use App\Service\CompetitionStatus;
use App\Service\MatchStatusResolver;
use App\Service\MatchEspionService;
use App\Service\TeamFavoriteCountryService;
use App\Service\TeamMatchPointsService;
use App\Service\TeamJokerService;
use App\Service\TeamRankingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/accueil', name: 'app_homepage', methods: ['GET'])]
    public function index(
        GameMatchRepository $gameMatchRepository,
        PronosticRepository $pronosticRepository,
        TeamMemberRepository $teamMemberRepository,
        CompetitionStatus $competitionStatus,
        ButRepository $butRepository,
        UserRepository $userRepository,
        ButeurGoalScoringService $buteurGoalScoringService,
        DefaultPronosticService $defaultPronosticService,
        MatchStatusResolver $matchStatusResolver,
        TeamJokerService $teamJokerService,
        TeamFavoriteCountryService $teamFavoriteCountryService,
        MatchEspionService $matchEspionService,
        PreCompetitionDashboardService $preCompetitionDashboard,
        TeamMatchPointsService $teamMatchPointsService,
        DashboardEditorialRepository $dashboardEditorialRepository,
        CompetitionStatsRandomPicker $competitionStatsRandomPicker,
        KernelInterface $kernel,
        BadgeFeature $badgeFeature,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $context = $this->collectHomepageContext(
            $user,
            $gameMatchRepository,
            $pronosticRepository,
            $teamMemberRepository,
            $competitionStatus,
            $butRepository,
            $userRepository,
            $buteurGoalScoringService,
            $defaultPronosticService,
            $matchStatusResolver,
            $teamJokerService,
            $teamFavoriteCountryService,
            $matchEspionService,
            $preCompetitionDashboard,
            $teamMatchPointsService,
            $dashboardEditorialRepository,
        );
        $context['dashboard_random_stats'] = $competitionStatsRandomPicker->pickDailyMany(2);
        $context['show_badge_simulate'] = 'dev' === $kernel->getEnvironment() && $badgeFeature->isEnabled();

        return $this->render('home/index.html.twig', $context);
    }

    #[Route('/accueil/apercu-v2', name: 'app_homepage_v2_preview', methods: ['GET'])]
    public function indexV2Preview(
        KernelInterface $kernel,
        GameMatchRepository $gameMatchRepository,
        PronosticRepository $pronosticRepository,
        TeamMemberRepository $teamMemberRepository,
        CompetitionStatus $competitionStatus,
        ButRepository $butRepository,
        UserRepository $userRepository,
        ButeurGoalScoringService $buteurGoalScoringService,
        DefaultPronosticService $defaultPronosticService,
        MatchStatusResolver $matchStatusResolver,
        TeamJokerService $teamJokerService,
        TeamFavoriteCountryService $teamFavoriteCountryService,
        MatchEspionService $matchEspionService,
        PreCompetitionDashboardService $preCompetitionDashboard,
        TeamMatchPointsService $teamMatchPointsService,
        DashboardEditorialRepository $dashboardEditorialRepository,
        TeamRankingSnapshotRepository $teamRankingSnapshotRepository,
    ): Response {
        if ('dev' !== $kernel->getEnvironment()) {
            throw new NotFoundHttpException();
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $context = $this->collectHomepageContext(
            $user,
            $gameMatchRepository,
            $pronosticRepository,
            $teamMemberRepository,
            $competitionStatus,
            $butRepository,
            $userRepository,
            $buteurGoalScoringService,
            $defaultPronosticService,
            $matchStatusResolver,
            $teamJokerService,
            $teamFavoriteCountryService,
            $matchEspionService,
            $preCompetitionDashboard,
            $teamMatchPointsService,
            $dashboardEditorialRepository,
        );

        $teamMember = $teamMemberRepository->findOneBy(['player' => $user]);
        $team = $teamMember?->getTeam();
        $teamRankingSnapshot = null;
        $latestRankingTeamsCount = 0;
        if ($team instanceof Team) {
            $latestRanking = $teamRankingSnapshotRepository->findLatestRanking();
            $latestRankingTeamsCount = \count($latestRanking);
            foreach ($latestRanking as $snapshot) {
                if ((int) $snapshot->getTeam()?->getId() === (int) $team->getId()) {
                    $teamRankingSnapshot = $snapshot;
                    break;
                }
            }
        }

        $userRankingPoints = null;
        $userRankingPosition = null;
        foreach ($context['ranking_summary'] as $position => $row) {
            if ($row['email'] === $user->getEmail()) {
                $userRankingPoints = $row['totalPoints'];
                $userRankingPosition = $position + 1;
                break;
            }
        }

        $context['team'] = $team;
        $context['team_ranking_snapshot'] = $teamRankingSnapshot;
        $context['latest_ranking_teams_count'] = $latestRankingTeamsCount;
        $context['user_ranking_points'] = $userRankingPoints;
        $context['user_ranking_position'] = $userRankingPosition;
        $context['ui_preview'] = true;

        return $this->render('home/index_v2_preview.html.twig', $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectHomepageContext(
        User $user,
        GameMatchRepository $gameMatchRepository,
        PronosticRepository $pronosticRepository,
        TeamMemberRepository $teamMemberRepository,
        CompetitionStatus $competitionStatus,
        ButRepository $butRepository,
        UserRepository $userRepository,
        ButeurGoalScoringService $buteurGoalScoringService,
        DefaultPronosticService $defaultPronosticService,
        MatchStatusResolver $matchStatusResolver,
        TeamJokerService $teamJokerService,
        TeamFavoriteCountryService $teamFavoriteCountryService,
        MatchEspionService $matchEspionService,
        PreCompetitionDashboardService $preCompetitionDashboard,
        TeamMatchPointsService $teamMatchPointsService,
        DashboardEditorialRepository $dashboardEditorialRepository,
    ): array {
        $now = new \DateTimeImmutable();
        $liveMatches = [];
        foreach ($gameMatchRepository->findCandidatesForLiveDisplay($now) as $candidate) {
            if ($matchStatusResolver->isMatchLive($candidate, $now)) {
                $liveMatches[] = $candidate;
            }
        }

        $liveMatchIds = [];
        foreach ($liveMatches as $liveMatch) {
            $liveMatchIds[(int) $liveMatch->getId()] = true;
        }

        $lastCompletedMatchday = $gameMatchRepository->findLastCompletedMatchday($now);
        $nextMatchday = $gameMatchRepository->findNextMatchday();
        $rankingSummary = $pronosticRepository->findRankingSummary();

        $dashboardMatches = [];
        foreach ($liveMatches as $match) {
            $dashboardMatches[(int) $match->getId()] = $match;
        }
        if (null !== $lastCompletedMatchday) {
            foreach ($lastCompletedMatchday['matches'] as $match) {
                if (!isset($liveMatchIds[(int) $match->getId()])) {
                    $dashboardMatches[(int) $match->getId()] = $match;
                }
            }
        }
        if (null !== $nextMatchday) {
            foreach ($nextMatchday['matches'] as $match) {
                if (!isset($liveMatchIds[(int) $match->getId()])) {
                    $dashboardMatches[(int) $match->getId()] = $match;
                }
            }
        }
        $dashboardMatchList = array_values($dashboardMatches);
        $dashboardMatchIds = array_values(array_filter(array_map(
            static fn (GameMatch $m): ?int => $m->getId(),
            $dashboardMatchList,
        )));
        $goalsByMatchId = $butRepository->findGoalRowsIndexedByMatchIds($dashboardMatchIds);

        $defaultPronosticService->ensureDefaultsForUser($user, $dashboardMatchList);

        $partnerIds = $teamMemberRepository->findPartnerPlayerIds($user);
        $teamMember = $teamMemberRepository->findOneBy(['player' => $user]);
        $team = $teamMember?->getTeam();
        $joker_usage_by_match_id = $team instanceof Team
            ? $teamJokerService->buildUsageSummaryByMatchIdForTeam($team)
            : [];
        $espion_intel_by_match_id = $team instanceof Team
            ? $matchEspionService->buildIntelByMatchIdForTeam($team, $dashboardMatchList, $now)
            : [];

        $buteur_stats = null;
        $buteurChoisi = $user->getButeurChoisi();
        if ($buteurChoisi instanceof Buteur) {
            $buteurId = (int) $buteurChoisi->getId();
            $selections = $userRepository->countWithButeurChoisiId($buteurId);
            $buteur_stats = [
                'goals' => $butRepository->countForButeur($buteurChoisi),
                'points' => $butRepository->sumPointsAttribuesForButeur($buteurChoisi),
                'cote' => $buteurGoalScoringService->getCurrentCoefficientForButeur($buteurChoisi),
                'points_per_goal' => $buteurGoalScoringService->getPointsPerGoalForButeur($buteurChoisi),
                'selections' => $selections,
                'total_players' => $userRepository->countWithButeurChoisi(),
            ];
        }

        $team_favorite_highlight = $team instanceof Team
            ? $teamFavoriteCountryService->buildMatchCardHighlight($team, $dashboardMatchList)
            : null;
        $team_match_points_by_match_id = $team instanceof Team
            ? $teamMatchPointsService->buildPointsByMatchIdForTeam($team, $dashboardMatchList, $goalsByMatchId)
            : [];
        $publishedEditorials = $dashboardEditorialRepository->findPublishedAtOrBeforeOrdered($now);
        $dashboardEditorial = $publishedEditorials[0] ?? null;
        $dashboardEditorialsArchive = [] !== $publishedEditorials
            ? \array_slice($publishedEditorials, 1)
            : [];

        return [
            'live_matches' => $liveMatches,
            'last_completed_matchday' => $lastCompletedMatchday,
            'next_matchday' => $nextMatchday,
            'ranking_summary' => $rankingSummary,
            'pronostics_by_match_id' => $pronosticRepository->findIndexedByPlayerAndMatches($user, $dashboardMatchList),
            'partner_pronostics_by_match_id' => $pronosticRepository->findIndexedByPlayersAndMatches($partnerIds, $dashboardMatchList),
            'now' => $now,
            'competition_started' => $competitionStatus->isStarted(),
            'cotisation_payee' => $user->isCotisationPayee(),
            'prono_access_blocked' => !$user->isCotisationPayee(),
            'dashboard_partners' => $teamMemberRepository->findPartnerUsers($user),
            'buteurs_pris_par_autres_equipes' => $teamMemberRepository->findButeursChoisisParAutresEquipes($user),
            'buteur_stats' => $buteur_stats,
            'joker_usage_by_match_id' => $joker_usage_by_match_id,
            'espion_intel_by_match_id' => $espion_intel_by_match_id,
            'show_joker_ui' => true,
            'team_favorite_highlight' => $team_favorite_highlight,
            'precomp_checklist' => $preCompetitionDashboard->buildChecklist($user),
            'goals_by_match_id' => $goalsByMatchId,
            'has_live_matches' => [] !== $liveMatches,
            'team_match_points_by_match_id' => $team_match_points_by_match_id,
            'dashboard_editorial' => $dashboardEditorial,
            'dashboard_editorials_archive' => $dashboardEditorialsArchive,
        ];
    }

    #[Route('/accueil/buteur', name: 'app_dashboard_buteur_save', methods: ['POST'])]
    public function saveDashboardButeur(
        Request $request,
        EntityManagerInterface $entityManager,
        ButeurRepository $buteurRepository,
        CompetitionStatus $competitionStatus,
        ButeurGoalScoringService $buteurGoalScoringService,
        GameMatchRepository $gameMatchRepository,
        TeamRankingService $teamRankingService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('dashboard_buteur', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée, veuillez réessayer.');

            return $this->redirectAfterDashboardButeurSave($request);
        }

        if ($competitionStatus->isStarted()) {
            $this->addFlash('danger', 'La compétition a déjà commencé : le buteur ne peut plus être modifié depuis le tableau de bord.');

            return $this->redirectAfterDashboardButeurSave($request);
        }

        if (!$user->isCotisationPayee()) {
            $this->addFlash('danger', 'Réglez votre cotisation pour pouvoir choisir votre buteur.');

            return $this->redirectAfterDashboardButeurSave($request);
        }

        $id = $request->request->get('buteur_id');
        if (!is_numeric($id)) {
            $this->addFlash('danger', 'Merci de sélectionner un buteur dans la liste.');

            return $this->redirectAfterDashboardButeurSave($request);
        }

        $buteur = $buteurRepository->find((int) $id);
        if (!$buteur instanceof Buteur) {
            $this->addFlash('danger', 'Buteur introuvable.');

            return $this->redirectAfterDashboardButeurSave($request);
        }

        if (!$buteur->isActif()) {
            $this->addFlash('danger', 'Ce joueur n\'est plus disponible pour le choix du buteur.');

            return $this->redirectAfterDashboardButeurSave($request);
        }

        $user->setButeurChoisi($buteur);
        $entityManager->flush();

        $this->afterButeurChoiceChanged($buteurGoalScoringService, $gameMatchRepository, $teamRankingService);

        $this->addFlash('success', 'Votre buteur a été enregistré.');

        return $this->redirectAfterDashboardButeurSave($request);
    }

    #[Route('/accueil/buteur/effacer', name: 'app_dashboard_buteur_clear', methods: ['POST'])]
    public function clearDashboardButeur(
        Request $request,
        EntityManagerInterface $entityManager,
        CompetitionStatus $competitionStatus,
        ButeurGoalScoringService $buteurGoalScoringService,
        GameMatchRepository $gameMatchRepository,
        TeamRankingService $teamRankingService,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('dashboard_buteur_clear', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée, veuillez réessayer.');

            return $this->redirectAfterDashboardButeurSave($request);
        }

        if ($competitionStatus->isStarted()) {
            $this->addFlash('danger', 'La compétition a déjà commencé : le buteur ne peut plus être modifié.');

            return $this->redirectAfterDashboardButeurSave($request);
        }

        if (!$user->getButeurChoisi() instanceof Buteur) {
            $this->addFlash('warning', 'Vous n\'avez pas de buteur sélectionné.');

            return $this->redirectAfterDashboardButeurSave($request);
        }

        $user->setButeurChoisi(null);
        $entityManager->flush();

        $this->afterButeurChoiceChanged($buteurGoalScoringService, $gameMatchRepository, $teamRankingService);

        $this->addFlash('success', 'Votre choix de buteur a été retiré.');

        return $this->redirectAfterDashboardButeurSave($request);
    }

    private function afterButeurChoiceChanged(
        ButeurGoalScoringService $buteurGoalScoringService,
        GameMatchRepository $gameMatchRepository,
        TeamRankingService $teamRankingService,
    ): void {
        $buteurGoalScoringService->rescoreAll();
        $latestMatch = $gameMatchRepository->findLatestFinishedMatch();
        if (null !== $latestMatch) {
            $teamRankingService->rebuildSnapshotsFromMatch($latestMatch);
        }
    }

    private function redirectAfterDashboardButeurSave(Request $request): Response
    {
        $return = $request->request->getString('_return');

        if (str_starts_with($return, 'country:')) {
            $countryId = (int) substr($return, 8);
            if ($countryId > 0) {
                return $this->redirectToRoute('app_country_players', ['id' => $countryId]);
            }
        }

        if ('account' === $return) {
            return $this->redirect($this->generateUrl('app_account').'#buteur');
        }

        return $this->redirectToRoute('app_homepage');
    }
}
