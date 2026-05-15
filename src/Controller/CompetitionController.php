<?php

namespace App\Controller;

use App\Entity\GameMatch;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Repository\GameMatchRepository;
use App\Repository\TeamRepository;
use App\Service\GroupStandingsBuilder;
use App\Repository\PronosticRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRankingSnapshotRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CompetitionController extends AbstractController
{
    #[Route('/matchs', name: 'app_matches', methods: ['GET'])]
    public function matches(
        GameMatchRepository $gameMatchRepository,
        PronosticRepository $pronosticRepository,
        TeamMemberRepository $teamMemberRepository,
    ): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $matches = $gameMatchRepository->findBy([], ['dateHeure' => 'ASC']);
        $partnerIds = $teamMemberRepository->findPartnerPlayerIds($user);

        $now = new \DateTimeImmutable();
        $matchdayNav = $this->buildMatchdayNavEntries($matches);

        return $this->render('competition/matches.html.twig', [
            'matches' => $matches,
            'matchday_nav' => $matchdayNav,
            'featured_matchday_key' => $this->resolveFeaturedMatchdayKey($matchdayNav, $now),
            'pronostics_by_match_id' => $pronosticRepository->findIndexedByPlayerAndMatches($user, $matches),
            'partner_pronostics_by_match_id' => $pronosticRepository->findIndexedByPlayersAndMatches($partnerIds, $matches),
            'now' => $now,
            'prono_access_blocked' => !$user->isCotisationPayee(),
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
            $key = $dateHeure->format('Y-m-d');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $entries[] = [
                'anchor' => 'journee-'.$key,
                'date_key' => $key,
                'date_for_label' => $dateHeure->setTime(0, 0, 0),
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

        $todayKey = $now->format('Y-m-d');
        foreach ($entries as $entry) {
            if ($entry['date_key'] === $todayKey) {
                return $todayKey;
            }
        }

        $todayStart = $now->setTime(0, 0, 0);
        foreach ($entries as $entry) {
            $day = $entry['date_for_label'];
            if ($day instanceof \DateTimeImmutable && $day >= $todayStart) {
                return $entry['date_key'];
            }
        }

        return $entries[array_key_last($entries)]['date_key'];
    }

    #[Route('/matchs/{id}/pronostics', name: 'app_match_pronostics', methods: ['GET'])]
    public function matchPronostics(GameMatch $match, PronosticRepository $pronosticRepository): Response
    {
        if (!$this->isMatchFinished($match)) {
            $this->addFlash('danger', 'Les pronostics de ce match seront visibles apres le score final.');

            return $this->redirectToRoute('app_matches');
        }

        return $this->render('competition/match_pronostics.html.twig', [
            'match' => $match,
            'pronostics' => $pronosticRepository->findByMatchWithTeamMembers($match),
        ]);
    }

    #[Route('/groupes', name: 'app_groups', methods: ['GET'])]
    public function groups(GroupStandingsBuilder $groupStandingsBuilder): Response
    {
        return $this->render('competition/groups.html.twig', [
            'standings_by_group' => $groupStandingsBuilder->build(),
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

        return $this->render('competition/team_show.html.twig', [
            'team' => $team,
            'team_members' => $members,
            'played_rows' => $playedRows,
            'team_ranking_snapshot' => $teamRankingSnapshot,
            'latest_ranking_teams_count' => count($latestRanking),
            'latest_ranking_match' => $latestRankingMatch,
            'ranking_evolution_by_day' => $this->buildRankingEvolutionByMatchDay($team, $teamRankingSnapshotRepository),
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
            $dayKey = $match->getDateHeure()->format('Y-m-d');
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
                'date_display' => $match->getDateHeure()->setTime(0, 0, 0),
                'position' => $position,
                'total_points' => $snapshot->getTotalPoints(),
                'teams_count' => $teamsCount,
                'last_match' => $match,
                'rank_bar_percent' => (int) round(($teamsCount - $position + 1) / $teamsCount * 100),
            ];
        }

        return $rows;
    }

    private function isMatchFinished(GameMatch $match): bool
    {
        $dateHeure = $match->getDateHeure();

        return 'FINISHED' === $match->getStatut()
            || (
                null !== $match->getScoreDomicile()
                && null !== $match->getScoreExterieur()
                && null !== $dateHeure
                && $dateHeure < new \DateTimeImmutable()
            );
    }
}
