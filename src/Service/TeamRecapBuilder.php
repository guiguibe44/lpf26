<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Pronostic;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Entity\TeamMember;
use App\Entity\TeamRankingSnapshot;
use App\Entity\User;
use App\Repository\ButRepository;
use App\Repository\PronosticRepository;
use App\Repository\TeamJokerUsageRepository;
use App\Repository\TeamRankingSnapshotRepository;
use App\TeamRecap\TeamRecapGifSlot;

/**
 * Construit le contexte Twig du récap d’équipe pour une période donnée.
 */
final class TeamRecapBuilder
{
    public function __construct(
        private readonly PronosticRepository $pronosticRepository,
        private readonly ButRepository $butRepository,
        private readonly TeamMatchPointsService $teamMatchPointsService,
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly JokerDefenseService $jokerDefenseService,
        private readonly TeamRankingSnapshotRepository $teamRankingSnapshotRepository,
        private readonly TeamRecapMvpResolver $mvpResolver,
        private readonly TeamRecapFunCopy $funCopy,
        private readonly BiDailyRecapSchedule $schedule,
        private readonly TeamRecapJokerGifResolver $jokerGifResolver,
        private readonly TeamRecapGifPicker $teamRecapGifPicker,
    ) {
    }

    /**
     * @return array<string, mixed>|null null si aucun match sur la période
     */
    public function buildForTeam(
        Team $team,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
        array $matches,
    ): ?array {
        if ([] === $matches) {
            return null;
        }

        $teamId = (int) $team->getId();
        $members = $this->sortedMembers($team);
        if ([] === $members) {
            return null;
        }

        $matchIds = array_map(static fn (GameMatch $m): int => (int) $m->getId(), $matches);
        $goalsByMatchId = $this->butRepository->findGoalRowsIndexedByMatchIds($matchIds);
        $teamPointsByMatchId = $this->teamMatchPointsService->buildPointsByMatchIdForTeam($team, $matches, $goalsByMatchId);

        $pronostics = $this->pronosticRepository->findForTeamMembersOnPlayedMatches($team);
        $pronosticByMatchAndUser = [];
        foreach ($pronostics as $prono) {
            $match = $prono->getMatch();
            $player = $prono->getJoueur();
            if (!$match instanceof GameMatch || !$player instanceof User || null === $player->getId()) {
                continue;
            }
            $mid = (int) $match->getId();
            if (!\in_array($mid, $matchIds, true)) {
                continue;
            }
            $pronosticByMatchAndUser[$mid][(int) $player->getId()] = $prono;
        }

        $rankedPlayers = $this->mvpResolver->rankMembers($members, $matches, $pronosticByMatchAndUser, $goalsByMatchId);
        $totalTeamPoints = array_sum($teamPointsByMatchId);
        $periodLabel = $this->schedule->formatPeriodLabel($periodStart, $periodEnd);
        $teamName = (string) $team->getName();

        $champion = $rankedPlayers[0] ?? null;
        $laggard = $rankedPlayers[\count($rankedPlayers) - 1] ?? null;
        $laggardBlock = null;
        $championTease = null;

        if (
            null !== $laggard
            && null !== $champion
            && \count($rankedPlayers) >= 2
            && (int) $laggard['user_id'] !== (int) $champion['user_id']
        ) {
            $laggardCopy = $this->funCopy->buildLaggardCopy(
                $laggard['nickname'],
                $laggard['points'],
            );
            $laggardBlock = array_merge($laggard, $laggardCopy);
            $championTease = $this->funCopy->buildChampionTease(
                $champion['nickname'],
                $champion['points'],
                $laggard['nickname'],
                $champion['points'] - $laggard['points'],
            );
        }

        $matchRows = [];
        $bigballsAttempted = 0;
        $bigballsSucceeded = 0;

        foreach ($matches as $match) {
            $mid = (int) $match->getId();
            $playerCells = [];
            $scoresByUser = [];

            foreach ($members as $member) {
                $player = $member->getPlayer();
                $uid = $player?->getId();
                $prono = (null !== $uid) ? ($pronosticByMatchAndUser[$mid][(int) $uid] ?? null) : null;
                if ($prono instanceof Pronostic) {
                    $scoresByUser[(int) $uid] = sprintf('%d-%d', $prono->getScoreDomicile(), $prono->getScoreExterieur());
                }

                $playerCells[] = $this->buildPlayerCell($member, $prono);
            }

            $bb = $this->resolveBigBallsForMatch($scoresByUser, $playerCells);
            if ($bb['attempted']) {
                ++$bigballsAttempted;
            }
            if ($bb['succeeded']) {
                ++$bigballsSucceeded;
            }

            $matchRows[] = [
                'label' => $this->matchLabel($match),
                'score' => sprintf('%d-%d', (int) $match->getScoreDomicile(), (int) $match->getScoreExterieur()),
                'date' => $match->getDateHeure()?->setTimezone(new \DateTimeZone(BiDailyRecapSchedule::TIMEZONE))->format('d/m H:i') ?? '—',
                'team_points' => (int) ($teamPointsByMatchId[$mid] ?? 0),
                'players' => $playerCells,
                'bigballs' => $bb,
            ];
        }

        $goalRows = $this->buildGoalRows($members, $matches, $goalsByMatchId);
        $ranking = $this->buildRankingBlock($team, $periodStart, $periodEnd);
        $rankingCheer = null;
        if (null !== $ranking) {
            $rankingCheer = $this->funCopy->buildRankingCheer(
                (int) ($ranking['delta_positions'] ?? 0),
                (int) ($ranking['delta_points'] ?? 0),
            );
        }

        [$jokersPlaced, $jokersSuffered] = $this->buildJokerBlocks($team, $teamId, $matches);
        $subjectGifSlot = TeamRecapGifSlot::subjectCodeForTeamPoints($totalTeamPoints);
        $recapGifUrl = [] !== $jokersPlaced
            ? $this->jokerGifResolver->resolveAbsoluteUrl($team, $matches, $teamPointsByMatchId)
            : $this->teamRecapGifPicker->pickRandomAbsoluteUrl($subjectGifSlot);

        if (!$this->teamHadActivityOnPeriod($matchIds, $pronosticByMatchAndUser, $totalTeamPoints, $goalRows, $jokersPlaced, $jokersSuffered)) {
            return null;
        }

        return [
            'team_name' => $teamName,
            'period_label' => $periodLabel,
            'intro_line' => $this->funCopy->buildIntro($teamName, $periodLabel, $totalTeamPoints, $rankedPlayers),
            'total_team_points' => $totalTeamPoints,
            'matches_count' => \count($matches),
            'laggard' => $laggardBlock,
            'champion_tease' => $championTease,
            'matches' => $matchRows,
            'bigballs_summary' => [
                'attempted' => $bigballsAttempted,
                'succeeded' => $bigballsSucceeded,
            ],
            'goals' => $goalRows,
            'ranking' => $ranking,
            'ranking_cheer' => $rankingCheer,
            'jokers_placed' => $jokersPlaced,
            'jokers_suffered' => $jokersSuffered,
            'subject_gif_slot' => $subjectGifSlot,
            'recap_gif_url' => $recapGifUrl,
        ];
    }

    /**
     * @return list<TeamMember>
     */
    private function sortedMembers(Team $team): array
    {
        $members = $team->getMembers()->toArray();
        usort($members, static fn (TeamMember $a, TeamMember $b): int => $a->getJoinedAt() <=> $b->getJoinedAt());

        return $members;
    }

    /**
     * @return array{nickname: string, prono: ?string, points: ?int, outcome: ?string, bigballs: bool}
     */
    private function buildPlayerCell(TeamMember $member, ?Pronostic $prono): array
    {
        if (!$prono instanceof Pronostic) {
            return [
                'nickname' => (string) $member->getNickname(),
                'prono' => null,
                'points' => null,
                'outcome' => null,
                'bigballs' => false,
            ];
        }

        $base = $prono->getPointsBase();
        $outcome = match ($base) {
            30 => 'score exact',
            10 => 'bon 1/N/2',
            default => 0 === (int) round((float) ($prono->getPoints() ?? 0)) ? 'raté' : 'points',
        };

        return [
            'nickname' => (string) $member->getNickname(),
            'prono' => sprintf('%d-%d', $prono->getScoreDomicile(), $prono->getScoreExterieur()),
            'points' => (int) round((float) ($prono->getPoints() ?? 0)),
            'outcome' => $outcome,
            'bigballs' => $prono->isPriseRisque(),
        ];
    }

    /**
     * @param array<int, string> $scoresByUser
     * @param list<array{bigballs: bool, outcome: ?string}> $playerCells
     *
     * @return array{attempted: bool, succeeded: bool}
     */
    private function resolveBigBallsForMatch(array $scoresByUser, array $playerCells): array
    {
        $uniqueScores = array_unique(array_values($scoresByUser));
        $attempted = \count($scoresByUser) >= 2 && 1 === \count($uniqueScores);

        $succeeded = false;
        if ($attempted) {
            foreach ($playerCells as $cell) {
                if (\in_array($cell['outcome'] ?? '', ['score exact', 'bon 1/N/2'], true)) {
                    $succeeded = true;
                    break;
                }
            }
        }

        return ['attempted' => $attempted, 'succeeded' => $succeeded];
    }

    private function matchLabel(GameMatch $match): string
    {
        $home = $match->getPaysDomicile()?->getNom() ?? '?';
        $away = $match->getPaysExterieur()?->getNom() ?? '?';

        return sprintf('%s — %s', $home, $away);
    }

    /**
     * @param list<TeamMember> $members
     * @param list<GameMatch>  $matches
     *
     * @return list<array{nickname: string, buteur: string, match: string, minute: ?int, points: int}>
     */
    private function buildGoalRows(array $members, array $matches, array $goalsByMatchId): array
    {
        $nicknameByButeurId = [];
        $buteurNameById = [];
        foreach ($members as $member) {
            $buteur = $member->getPlayer()?->getButeurChoisi();
            if (null === $buteur?->getId()) {
                continue;
            }
            $nicknameByButeurId[(int) $buteur->getId()] = (string) $member->getNickname();
            $buteurNameById[(int) $buteur->getId()] = (string) $buteur->getNom();
        }

        $rows = [];
        foreach ($matches as $match) {
            $mid = (int) $match->getId();
            $label = $this->matchLabel($match);
            foreach ($goalsByMatchId[$mid] ?? [] as $goal) {
                $buteurId = (int) ($goal['buteur_id'] ?? 0);
                if (!isset($nicknameByButeurId[$buteurId])) {
                    continue;
                }
                $rows[] = [
                    'nickname' => $nicknameByButeurId[$buteurId],
                    'buteur' => $goal['name'] ?? ($buteurNameById[$buteurId] ?? 'Buteur'),
                    'match' => $label,
                    'minute' => isset($goal['minute']) ? (int) $goal['minute'] : null,
                    'points' => (int) ($goal['points'] ?? 0),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildRankingBlock(Team $team, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd): ?array
    {
        $before = $this->teamRankingSnapshotRepository->findLatestForTeamBefore($team, $periodStart);
        $after = $this->teamRankingSnapshotRepository->findLatestForTeamUpTo($team, $periodEnd);

        if (!$after instanceof TeamRankingSnapshot) {
            return null;
        }

        $matchRef = $after->getMatchRef();
        if (!$matchRef instanceof GameMatch) {
            return null;
        }

        $teamsCount = $this->teamRankingSnapshotRepository->countTeamsForMatch($matchRef);

        $beforePosition = $before?->getPosition();
        $beforePoints = $before instanceof TeamRankingSnapshot ? (int) round($before->getTotalPoints()) : null;

        $afterPosition = $after->getPosition();
        $afterPoints = (int) round($after->getTotalPoints());

        $deltaPositions = null !== $beforePosition ? $beforePosition - $afterPosition : null;
        $deltaPoints = null !== $beforePoints ? $afterPoints - $beforePoints : null;

        return [
            'before' => null !== $beforePosition ? [
                'position' => $beforePosition,
                'total' => $beforePoints,
                'teams_count' => $teamsCount,
            ] : null,
            'after' => [
                'position' => $afterPosition,
                'total' => $afterPoints,
                'teams_count' => $teamsCount,
            ],
            'delta_positions' => $deltaPositions ?? 0,
            'delta_points' => $deltaPoints ?? 0,
        ];
    }

    /**
     * @param list<GameMatch> $matches
     *
     * @return array{0: list<array<string, string>>, 1: list<array<string, string>>}
     */
    private function buildJokerBlocks(Team $team, int $teamId, array $matches): array
    {
        $placed = [];
        $suffered = [];

        foreach ($matches as $match) {
            $matchLabel = $this->matchLabel($match);

            foreach ($this->teamJokerUsageRepository->findByMatch($match) as $usage) {
                if (!$usage instanceof TeamJokerUsage) {
                    continue;
                }

                $jokerName = (string) ($usage->getJoker()?->getName() ?? 'Joker');
                $ownerId = (int) ($usage->getTeam()?->getId() ?? 0);

                if ($ownerId === $teamId) {
                    $placed[] = [
                        'name' => $jokerName,
                        'match' => $matchLabel,
                    ];
                    continue;
                }

                $targetId = (int) ($usage->getTargetTeam()?->getId() ?? 0);
                if ($targetId === $teamId && JokerDefenseService::isOffensiveAgainstTeam($usage->getJoker()?->getCode())) {
                    $blocked = $this->jokerDefenseService->isTeamProtectedOnMatch($team, $match);
                    $suffered[] = [
                        'name' => $jokerName,
                        'match' => $matchLabel,
                        'blocked' => $blocked,
                    ];
                }
            }

            if ($this->jokerDefenseService->isTeamProtectedByFavoriteOnGroupMatch($team, $match)) {
                $suffered[] = [
                    'name' => 'Équipe favorite (protection)',
                    'match' => $matchLabel,
                    'blocked' => true,
                ];
            }
        }

        return [$placed, $suffered];
    }

    /**
     * @param list<int> $matchIds
     * @param array<int, array<int, Pronostic>> $pronosticByMatchAndUser
     * @param list<array<string, mixed>> $goalRows
     * @param list<array<string, mixed>> $jokersPlaced
     * @param list<array<string, mixed>> $jokersSuffered
     */
    private function teamHadActivityOnPeriod(
        array $matchIds,
        array $pronosticByMatchAndUser,
        int $totalTeamPoints,
        array $goalRows,
        array $jokersPlaced,
        array $jokersSuffered,
    ): bool {
        if ($totalTeamPoints > 0 || [] !== $goalRows || [] !== $jokersPlaced || [] !== $jokersSuffered) {
            return true;
        }

        foreach ($matchIds as $mid) {
            if ([] !== ($pronosticByMatchAndUser[$mid] ?? [])) {
                return true;
            }
        }

        return false;
    }
}
