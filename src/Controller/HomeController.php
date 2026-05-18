<?php

namespace App\Controller;

use App\Entity\Buteur;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\ButeurRepository;
use App\Repository\ButRepository;
use App\Repository\GameMatchRepository;
use App\Repository\PronosticRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\UserRepository;
use App\Service\ButeurGoalScoringService;
use App\Service\DefaultPronosticService;
use App\Service\CompetitionStatus;
use App\Service\MatchStatusResolver;
use App\Service\MatchEspionService;
use App\Service\TeamFavoriteCountryService;
use App\Service\TeamJokerService;
use App\Service\TeamRankingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

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
            $buteur_stats = [
                'goals' => $butRepository->countForButeur($buteurChoisi),
                'points' => $butRepository->sumPointsAttribuesForButeur($buteurChoisi),
                'cote' => $buteurGoalScoringService->getCurrentCoefficientForButeur($buteurChoisi),
                'selections' => $userRepository->countWithButeurChoisiId($buteurId),
                'total_players' => $userRepository->countWithButeurChoisi(),
            ];
        }

        $team_favorite_highlight = $team instanceof Team
            ? $teamFavoriteCountryService->buildMatchCardHighlight($team, $dashboardMatchList)
            : null;

        return $this->render('home/index.html.twig', [
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
        ]);
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

        $user->setButeurChoisi($buteur);
        $entityManager->flush();

        $buteurGoalScoringService->rescoreAll();
        $latestMatch = $gameMatchRepository->findLatestFinishedMatch();
        if (null !== $latestMatch) {
            $teamRankingService->rebuildSnapshotsFromMatch($latestMatch);
        }

        $this->addFlash('success', 'Votre buteur a été enregistré.');

        return $this->redirectAfterDashboardButeurSave($request);
    }

    private function redirectAfterDashboardButeurSave(Request $request): Response
    {
        $url = 'account' === $request->request->getString('_return')
            ? $this->generateUrl('app_account').'#tab-compte'
            : $this->generateUrl('app_homepage');

        return $this->redirect($url);
    }
}
