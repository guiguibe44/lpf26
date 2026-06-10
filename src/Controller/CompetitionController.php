<?php

namespace App\Controller;

use App\DateTime\AppTimezone;
use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Repository\ButRepository;
use App\Repository\GameMatchRepository;
use App\Repository\TeamRepository;
use App\Service\DefaultPronosticService;
use App\Service\ButeurPickContextFactory;
use App\Service\CompetitionStatus;
use App\Service\CountrySquadPitchBuilder;
use App\Service\GroupKnockoutQualificationAnalyzer;
use App\Service\GroupStandingsBuilder;
use App\Service\KnockoutSchedulePresenter;
use App\Service\KdoMatchWinnerService;
use App\Service\MatchHubV2DemoPresenter;
use App\Service\MatchHubV2DiscussionFeedBuilder;
use App\Service\MatchLiveViewBuilder;
use App\Service\MatchStatusResolver;
use App\Service\MatchdayKey;
use App\Service\MatchEspionService;
use App\Service\TeamFavoriteCountryService;
use App\Service\TeamMatchPointsService;
use App\Service\TeamJokerService;
use App\Repository\PronosticRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRankingSnapshotRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

class CompetitionController extends AbstractController
{
    #[Route('/matchs', name: 'app_matches', methods: ['GET'])]
    public function matches(
        GameMatchRepository $gameMatchRepository,
        PronosticRepository $pronosticRepository,
        TeamMemberRepository $teamMemberRepository,
        DefaultPronosticService $defaultPronosticService,
        TeamJokerService $teamJokerService,
        TeamFavoriteCountryService $teamFavoriteCountryService,
        MatchEspionService $matchEspionService,
        ButRepository $butRepository,
        TeamMatchPointsService $teamMatchPointsService,
    ): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $matches = $gameMatchRepository->findBy([], ['dateHeure' => 'ASC']);
        $matchIds = array_values(array_filter(array_map(
            static fn (GameMatch $m): ?int => $m->getId(),
            $matches,
        )));
        $goalsByMatchId = $butRepository->findGoalRowsIndexedByMatchIds($matchIds);
        $defaultPronosticService->ensureDefaultsForUser($user, $matches);
        $partnerIds = $teamMemberRepository->findPartnerPlayerIds($user);
        $teamMember = $teamMemberRepository->findOneBy(['player' => $user]);
        $team = $teamMember?->getTeam();
        $joker_usage_by_match_id = $team instanceof Team
            ? $teamJokerService->buildUsageSummaryByMatchIdForTeam($team)
            : [];

        $now = new \DateTimeImmutable();
        $espion_intel_by_match_id = $team instanceof Team
            ? $matchEspionService->buildIntelByMatchIdForTeam($team, $matches, $now)
            : [];
        $matchdayNav = $this->buildMatchdayNavEntries($matches);
        $team_favorite_highlight = $team instanceof Team
            ? $teamFavoriteCountryService->buildMatchCardHighlight($team, $matches)
            : null;
        $team_match_points_by_match_id = $team instanceof Team
            ? $teamMatchPointsService->buildPointsByMatchIdForTeam($team, $matches, $goalsByMatchId)
            : [];

        return $this->render('competition/matches.html.twig', [
            'matches' => $matches,
            'matchday_nav' => $matchdayNav,
            'featured_matchday_key' => $this->resolveFeaturedMatchdayKey($matchdayNav, $now),
            'pronostics_by_match_id' => $pronosticRepository->findIndexedByPlayerAndMatches($user, $matches),
            'partner_pronostics_by_match_id' => $pronosticRepository->findIndexedByPlayersAndMatches($partnerIds, $matches),
            'now' => $now,
            'prono_access_blocked' => !$user->isCotisationPayee(),
            'joker_usage_by_match_id' => $joker_usage_by_match_id,
            'espion_intel_by_match_id' => $espion_intel_by_match_id,
            'show_joker_ui' => true,
            'team_favorite_highlight' => $team_favorite_highlight,
            'goals_by_match_id' => $goalsByMatchId,
            'team_match_points_by_match_id' => $team_match_points_by_match_id,
        ]);
    }

    /**
     * @param list<GameMatch> $matches
     *
     * @return list<array{anchor: string, date_key: string, date_for_label: \DateTimeImmutable|null}>
     */
    private function buildMatchdayNavEntries(array $matches): array
    {
        $entries = [];
        $seen = [];
        foreach ($matches as $match) {
            if (!$match instanceof GameMatch) {
                continue;
            }
            $dateHeure = $match->getDateHeure();
            if (!$dateHeure instanceof \DateTimeImmutable) {
                $key = 'date-inconnue';
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $entries[] = [
                    'anchor' => 'journee-date-inconnue',
                    'date_key' => $key,
                    'date_for_label' => null,
                ];

                continue;
            }
            $key = MatchdayKey::fromMatch($match);
            if (null === $key || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $dayBounds = MatchdayKey::dayBounds($key);
            $entries[] = [
                'anchor' => 'journee-'.$key,
                'date_key' => $key,
                'date_for_label' => $dayBounds['start'] ?? null,
            ];
        }

        return $entries;
    }

    /**
     * Journée du jour si des matchs, sinon la prochaine à venir, sinon la dernière passée.
     *
     * @param list<array{anchor: string, date_key: string, date_for_label: \DateTimeImmutable|null}> $entries
     */
    private function resolveFeaturedMatchdayKey(array $entries, \DateTimeImmutable $now): ?string
    {
        if ([] === $entries) {
            return null;
        }

        $todayKey = AppTimezone::todayKey($now);
        foreach ($entries as $entry) {
            if ($entry['date_key'] === $todayKey) {
                return $todayKey;
            }
        }

        $todayBounds = MatchdayKey::dayBounds($todayKey);
        $todayStart = $todayBounds['start'] ?? AppTimezone::toLocal($now)->setTime(0, 0, 0);
        foreach ($entries as $entry) {
            $day = $entry['date_for_label'];
            if ($day instanceof \DateTimeImmutable && $day >= $todayStart) {
                return $entry['date_key'];
            }
        }

        return $entries[array_key_last($entries)]['date_key'];
    }

    #[Route('/matchs/{id}/live', name: 'app_match_live', methods: ['GET'])]
    public function matchLive(
        GameMatch $match,
        MatchStatusResolver $matchStatusResolver,
    ): Response {
        if (!$matchStatusResolver->isMatchLive($match) && !$matchStatusResolver->isMatchFinished($match)) {
            $this->addFlash('warning', 'Le suivi en direct sera disponible au coup d\'envoi.');

            return $this->redirectToRoute('app_matches');
        }

        return $this->redirectToRoute('app_match_hub', ['id' => $match->getId()]);
    }

    #[Route('/matchs/{id}/simulateur', name: 'app_match_simulator', methods: ['GET'])]
    public function matchSimulator(
        GameMatch $match,
        MatchStatusResolver $matchStatusResolver,
        MatchLiveViewBuilder $matchLiveViewBuilder,
        ButRepository $butRepository,
    ): Response {
        if (!$matchStatusResolver->isMatchLive($match)) {
            return $this->redirectToRoute('app_match_hub', ['id' => $match->getId()]);
        }

        $scoreDomicile = $match->getScoreDomicile() ?? 0;
        $scoreExterieur = $match->getScoreExterieur() ?? 0;
        $liveView = $matchLiveViewBuilder->build($match, $scoreDomicile, $scoreExterieur);

        $matchGoals = [];
        $matchId = $match->getId();
        if (null !== $matchId) {
            $matchGoals = $butRepository->findGoalRowsIndexedByMatchIds([$matchId])[$matchId] ?? [];
        }

        return $this->render('competition/match_live.html.twig', [
            'match' => $match,
            'live_view' => $liveView,
            'match_goals' => $matchGoals,
            'simulate_url' => $this->generateUrl('app_match_pronostics_simulate', ['id' => $match->getId()]),
        ]);
    }

    #[Route('/matchs/{id}/pronostics/simuler', name: 'app_match_pronostics_simulate', methods: ['GET'])]
    public function matchPronosticsSimulate(
        GameMatch $match,
        Request $request,
        MatchStatusResolver $matchStatusResolver,
        MatchLiveViewBuilder $matchLiveViewBuilder,
    ): JsonResponse {
        if (!$matchStatusResolver->isMatchStarted($match)) {
            return new JsonResponse(['error' => 'Match non démarré.'], Response::HTTP_BAD_REQUEST);
        }

        $scoreDomicile = max(0, min(30, $request->query->getInt('domicile', $match->getScoreDomicile() ?? 0)));
        $scoreExterieur = max(0, min(30, $request->query->getInt('exterieur', $match->getScoreExterieur() ?? 0)));

        return $this->json($matchLiveViewBuilder->buildForJson($match, $scoreDomicile, $scoreExterieur));
    }

    #[Route('/matchs/{id}/pronostics', name: 'app_match_pronostics', methods: ['GET'])]
    public function matchPronostics(
        GameMatch $match,
        MatchStatusResolver $matchStatusResolver,
    ): Response {
        if (!$matchStatusResolver->isMatchFinished($match) && !$matchStatusResolver->isMatchLive($match)) {
            $this->addFlash('danger', 'Les pronostics de ce match seront visibles apres le score final.');

            return $this->redirectToRoute('app_matches');
        }

        return $this->redirectToRoute('app_match_hub', ['id' => $match->getId()]);
    }

    #[Route('/matchs/{id}/hub', name: 'app_match_hub', methods: ['GET'])]
    public function matchHub(
        GameMatch $match,
        MatchStatusResolver $matchStatusResolver,
        MatchLiveViewBuilder $matchLiveViewBuilder,
        ButRepository $butRepository,
        PronosticRepository $pronosticRepository,
        TeamMemberRepository $teamMemberRepository,
        DefaultPronosticService $defaultPronosticService,
        TeamJokerService $teamJokerService,
        TeamMatchPointsService $teamMatchPointsService,
        MatchHubV2DiscussionFeedBuilder $discussionFeedBuilder,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $now = new \DateTimeImmutable();
        $isLive = $matchStatusResolver->isMatchLive($match, $now);
        $isFinished = $matchStatusResolver->isMatchFinished($match);
        $isUpcoming = !$isLive && !$isFinished;

        if ($isUpcoming) {
            $this->addFlash('warning', 'Le hub match sera disponible au coup d\'envoi.');

            return $this->redirectToRoute('app_matches');
        }

        return $this->render('competition/match_hub.html.twig', $this->buildMatchHubViewData(
            $match,
            $user,
            $now,
            $isLive,
            $isFinished,
            $isUpcoming,
            $matchLiveViewBuilder,
            $butRepository,
            $pronosticRepository,
            $teamMemberRepository,
            $defaultPronosticService,
            $teamJokerService,
            $teamMatchPointsService,
            $discussionFeedBuilder,
        ));
    }

    #[Route('/matchs/{id}/hub-apercu', name: 'app_match_hub_v2_preview', methods: ['GET'])]
    public function matchHubV2Preview(GameMatch $match): Response
    {
        return $this->redirectToRoute('app_match_hub', ['id' => $match->getId()]);
    }

    #[Route('/matchs/hub-apercu-demo', name: 'app_match_hub_v2_demo', methods: ['GET'])]
    public function matchHubV2Demo(
        KernelInterface $kernel,
        Request $request,
        MatchHubV2DemoPresenter $demoPresenter,
    ): Response {
        if ('dev' !== $kernel->getEnvironment()) {
            throw new NotFoundHttpException();
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('competition/match_hub_v2_demo.html.twig', $demoPresenter->build(
            $request->query->getString('etat', 'live'),
            $user,
        ));
    }

    #[Route('/groupes', name: 'app_groups', methods: ['GET'])]
    public function groups(
        GroupStandingsBuilder $groupStandingsBuilder,
        GroupKnockoutQualificationAnalyzer $groupKnockoutQualificationAnalyzer,
    ): Response {
        return $this->render('competition/groups.html.twig', [
            'standings_by_group' => $groupKnockoutQualificationAnalyzer->enrich($groupStandingsBuilder->build()),
        ]);
    }

    #[Route('/groupes/pays/{id}', name: 'app_country_players', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function countryPlayers(
        Country $country,
        CountrySquadPitchBuilder $countrySquadPitchBuilder,
        ButeurPickContextFactory $buteurPickContextFactory,
        CompetitionStatus $competitionStatus,
    ): Response {
        $countryId = (int) $country->getId();

        return $this->render('competition/country_players.html.twig', [
            'country' => $country,
            'squad' => $countrySquadPitchBuilder->build($countryId),
            'buteur_pick' => $buteurPickContextFactory->create(
                $this->getUser() instanceof User ? $this->getUser() : null,
            ),
            'buteur_pick_return' => 'country:'.$countryId,
            'competition_started' => $competitionStatus->isStarted(),
        ]);
    }

    #[Route('/phases-finales', name: 'app_knockout', methods: ['GET'])]
    public function knockout(KnockoutSchedulePresenter $knockoutSchedulePresenter): Response
    {
        return $this->render('competition/knockout.html.twig', [
            'knockout_bracket' => $knockoutSchedulePresenter->buildBracket(),
        ]);
    }

    #[Route('/classement', name: 'app_ranking', methods: ['GET'])]
    public function ranking(
        TeamRankingSnapshotRepository $teamRankingSnapshotRepository,
        TeamRepository $teamRepository,
    ): Response {
        $ranking = $teamRankingSnapshotRepository->findLatestRanking();
        $rankingMatch = [] !== $ranking ? $ranking[0]->getMatchRef() : null;
        $teamsPreview = [] === $ranking ? $teamRepository->findAllOrderedByName() : [];

        return $this->render('competition/ranking.html.twig', [
            'ranking' => $ranking,
            'ranking_match' => $rankingMatch,
            'teams_preview' => $teamsPreview,
        ]);
    }

    #[Route('/classement/equipe/{id}', name: 'app_team_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function teamShow(
        int $id,
        TeamRepository $teamRepository,
        PronosticRepository $pronosticRepository,
        TeamRankingSnapshotRepository $teamRankingSnapshotRepository,
        \App\Service\Badge\BadgeDisplayBuilder $badgeDisplayBuilder,
    ): Response {
        $team = $teamRepository->findOneWithMembersAndPlayers($id);
        if (!$team instanceof Team) {
            throw $this->createNotFoundException();
        }

        $members = $team->getMembers()->toArray();
        usort($members, static function (TeamMember $a, TeamMember $b): int {
            return $a->getJoinedAt() <=> $b->getJoinedAt();
        });

        $pronostics = $pronosticRepository->findForTeamMembersOnPlayedMatches($team);

        $matchesById = [];
        $pronosticByMatchAndUser = [];
        foreach ($pronostics as $pronostic) {
            $match = $pronostic->getMatch();
            if (!$match instanceof GameMatch) {
                continue;
            }
            $mid = (int) $match->getId();
            $matchesById[$mid] = $match;
            $uid = (int) $pronostic->getJoueur()->getId();
            $pronosticByMatchAndUser[$mid][$uid] = $pronostic;
        }

        $matchList = array_values($matchesById);
        usort($matchList, static function (GameMatch $a, GameMatch $b): int {
            $ta = $a->getDateHeure()?->getTimestamp() ?? 0;
            $tb = $b->getDateHeure()?->getTimestamp() ?? 0;
            if ($tb !== $ta) {
                return $tb <=> $ta;
            }

            return ((int) $b->getId()) <=> ((int) $a->getId());
        });

        $playedRows = [];
        foreach ($matchList as $match) {
            $mid = (int) $match->getId();
            $cells = [];
            foreach ($members as $member) {
                $pid = $member->getPlayer()?->getId();
                $cells[] = (null !== $pid && isset($pronosticByMatchAndUser[$mid][$pid]))
                    ? $pronosticByMatchAndUser[$mid][$pid]
                    : null;
            }
            $playedRows[] = [
                'match' => $match,
                'pronostics' => $cells,
            ];
        }

        $latestRanking = $teamRankingSnapshotRepository->findLatestRanking();
        $latestRankingMatch = [] !== $latestRanking ? $latestRanking[0]->getMatchRef() : null;
        $teamRankingSnapshot = null;
        foreach ($latestRanking as $snapshot) {
            if ((int) $snapshot->getTeam()?->getId() === (int) $team->getId()) {
                $teamRankingSnapshot = $snapshot;
                break;
            }
        }

        $viewer = $this->getUser();
        $viewerUser = $viewer instanceof User ? $viewer : null;

        return $this->render('competition/team_show.html.twig', [
            'team' => $team,
            'team_members' => $members,
            'played_rows' => $playedRows,
            'team_ranking_snapshot' => $teamRankingSnapshot,
            'latest_ranking_teams_count' => count($latestRanking),
            'latest_ranking_match' => $latestRankingMatch,
            'ranking_evolution_by_day' => $this->buildRankingEvolutionByMatchDay($team, $teamRankingSnapshotRepository),
            'team_badges' => $badgeDisplayBuilder->buildForTeam($team, $viewerUser),
        ]);
    }

    /**
     * Rang et points apres le dernier match classe de chaque jour calendaire (pas une ligne par match).
     *
     * @return list<array{
     *     day: string,
     *     date_display: \DateTimeImmutable,
     *     position: int,
     *     total_points: float,
     *     teams_count: int,
     *     last_match: GameMatch,
     *     rank_bar_percent: int
     * }>
     */
    private function buildRankingEvolutionByMatchDay(Team $team, TeamRankingSnapshotRepository $teamRankingSnapshotRepository): array
    {
        $snapshots = $teamRankingSnapshotRepository->findSnapshotsForTeamOrderedByMatch($team);
        $lastSnapshotByDay = [];
        foreach ($snapshots as $snapshot) {
            $match = $snapshot->getMatchRef();
            if (!$match instanceof GameMatch || null === $match->getDateHeure()) {
                continue;
            }
            $dayKey = MatchdayKey::fromMatch($match);
            if (null === $dayKey) {
                continue;
            }
            $lastSnapshotByDay[$dayKey] = $snapshot;
        }
        ksort($lastSnapshotByDay);

        $rows = [];
        foreach ($lastSnapshotByDay as $dayKey => $snapshot) {
            $match = $snapshot->getMatchRef();
            if (!$match instanceof GameMatch || null === $match->getDateHeure()) {
                continue;
            }
            $teamsCount = max(1, $teamRankingSnapshotRepository->countTeamsForMatch($match));
            $position = $snapshot->getPosition();
            $rows[] = [
                'day' => $dayKey,
                'date_display' => MatchdayKey::dayStartForMatch($match),
                'position' => $position,
                'total_points' => $snapshot->getTotalPoints(),
                'teams_count' => $teamsCount,
                'last_match' => $match,
                'rank_bar_percent' => (int) round(($teamsCount - $position + 1) / $teamsCount * 100),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMatchHubViewData(
        GameMatch $match,
        User $user,
        \DateTimeImmutable $now,
        bool $isLive,
        bool $isFinished,
        bool $isUpcoming,
        MatchLiveViewBuilder $matchLiveViewBuilder,
        ButRepository $butRepository,
        PronosticRepository $pronosticRepository,
        TeamMemberRepository $teamMemberRepository,
        DefaultPronosticService $defaultPronosticService,
        TeamJokerService $teamJokerService,
        TeamMatchPointsService $teamMatchPointsService,
        MatchHubV2DiscussionFeedBuilder $discussionFeedBuilder,
    ): array {
        $defaultPronosticService->ensureDefaultsForUser($user, [$match]);

        $scoreDomicile = $match->getScoreDomicile() ?? 0;
        $scoreExterieur = $match->getScoreExterieur() ?? 0;
        $liveView = ($isLive || $isFinished)
            ? $matchLiveViewBuilder->build($match, $scoreDomicile, $scoreExterieur)
            : null;

        $matchGoals = [];
        $matchId = $match->getId();
        if (null !== $matchId) {
            $matchGoals = $butRepository->findGoalRowsIndexedByMatchIds([$matchId])[$matchId] ?? [];
        }

        $partnerIds = $teamMemberRepository->findPartnerPlayerIds($user);
        $pronostic = null !== $matchId
            ? ($pronosticRepository->findIndexedByPlayerAndMatches($user, [$match])[$matchId] ?? null)
            : null;
        $partnerPronostics = null !== $matchId
            ? ($pronosticRepository->findIndexedByPlayersAndMatches($partnerIds, [$match])[$matchId] ?? [])
            : [];

        $teamMember = $teamMemberRepository->findOneBy(['player' => $user]);
        $team = $teamMember?->getTeam();
        $matchJoker = $team instanceof Team && null !== $matchId
            ? ($teamJokerService->buildUsageSummaryByMatchIdForTeam($team)[$matchId] ?? null)
            : null;
        $teamMatchPoints = $team instanceof Team && null !== $matchId
            ? ($teamMatchPointsService->buildPointsByMatchIdForTeam($team, [$match], [$matchId => $matchGoals])[$matchId] ?? null)
            : null;

        $homeLabel = $match->getPaysDomicile()?->getNom() ?? 'Domicile';
        $awayLabel = $match->getPaysExterieur()?->getNom() ?? 'Extérieur';

        return [
            'match' => $match,
            'now' => $now,
            'is_live' => $isLive,
            'is_finished' => $isFinished,
            'is_upcoming' => $isUpcoming,
            'live_view' => $liveView,
            'match_goals' => $matchGoals,
            'pronostic' => $pronostic,
            'partner_pronostics' => $partnerPronostics,
            'match_joker' => $matchJoker,
            'team_match_points' => $teamMatchPoints,
            'discussion_feed' => $discussionFeedBuilder->buildMatchFeed(
                $match,
                $matchGoals,
                $pronosticRepository->findByMatchWithTeamMembers($match),
                $homeLabel,
                $awayLabel,
                $isUpcoming,
                $isFinished,
            ),
        ];
    }

}
