<?php

declare(strict_types=1);

namespace App\Service\Badge;

use App\Entity\BadgeDefinition;
use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\Team;
use App\Entity\User;
use App\Entity\Joker;
use App\Repository\BadgeDefinitionRepository;
use App\Repository\ButRepository;
use App\Repository\GameMatchRepository;
use App\Repository\PronosticRepository;
use App\Repository\TeamJokerUsageRepository;
use App\Repository\TeamMemberRepository;
use App\Repository\TeamRankingSnapshotRepository;
use App\Repository\TeamRepository;
use App\Repository\UserRepository;
use App\Service\BadgeFeature;
use App\Service\JokerDefenseService;
use App\Service\MatchdayKey;
use App\Service\PronosticScoreInversionService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Évalue et attribue les badges après rescoring d’un match ou sauvegarde de prono.
 */
final class BadgeEvaluator
{
    private const int EXACT_BASE = 30;
    private const int GOOD_BASE = 10;

    /** @var array<string, BadgeDefinition>|null */
    private ?array $badgesByCode = null;

    public function __construct(
        private readonly BadgeFeature $badgeFeature,
        private readonly BadgeDefinitionRepository $badgeDefinitionRepository,
        private readonly BadgeAwardGranter $awardGranter,
        private readonly PronosticRepository $pronosticRepository,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly TeamRepository $teamRepository,
        private readonly TeamRankingSnapshotRepository $teamRankingSnapshotRepository,
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly UserRepository $userRepository,
        private readonly ButRepository $butRepository,
        private readonly PronosticScoreInversionService $pronosticScoreInversionService,
        private readonly JokerDefenseService $jokerDefenseService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function evaluateAfterMatchRescore(GameMatch $match): void
    {
        if (!$this->badgeFeature->isEnabled()) {
            return;
        }

        if (null === $match->getScoreDomicile() || null === $match->getScoreExterieur()) {
            return;
        }

        $matchId = (int) $match->getId();
        if ($matchId <= 0) {
            return;
        }

        $playerTeamMap = $this->teamMemberRepository->findPlayerTeamMap();
        $matchPronostics = $this->pronosticRepository->findByMatchWithTeamMembers($match);
        $allScored = $this->pronosticRepository->findScoredPronosticsWithTeamMembers();
        $invertedTeamIds = $this->pronosticScoreInversionService->getTargetTeamIdsForMatch($match);
        $effectiveById = $this->pronosticScoreInversionService->buildEffectiveScoresByPronosticId(
            $matchPronostics,
            $playerTeamMap,
            $invertedTeamIds,
        );

        $this->evaluateMatchPronostics($match, $matchPronostics, $playerTeamMap, $effectiveById, $invertedTeamIds, $allScored);
        $this->evaluateMatchBigBalls($match, $matchPronostics, $playerTeamMap);
        $this->evaluateMatchJokers($match, $matchPronostics, $playerTeamMap);
        $this->evaluateCumulativePlayerStats($allScored, $playerTeamMap);
        $this->evaluateCumulativeTeamStats($allScored, $playerTeamMap);
        $this->evaluateRankingBadges($match);
        $this->evaluateButeurBadges();

        $this->entityManager->flush();
    }

    public function evaluateOnPronosticSaved(User $user, GameMatch $match): void
    {
        if (!$this->badgeFeature->isEnabled()) {
            return;
        }

        if (!$this->badgeFeature->isActiveForUser($user)) {
            return;
        }

        $this->evaluatePronosticParticipationBadges($user, $match);
        $this->entityManager->flush();
    }

    /**
     * @param list<Pronostic>           $matchPronostics
     * @param array<int, int>           $playerTeamMap
     * @param array<int, array{home: int, away: int, inverted: bool}> $effectiveById
     * @param array<int, true>          $invertedTeamIds
     * @param iterable<Pronostic>       $allScored
     */
    private function evaluateMatchPronostics(
        GameMatch $match,
        array $matchPronostics,
        array $playerTeamMap,
        array $effectiveById,
        array $invertedTeamIds,
        iterable $allScored,
    ): void {
        $matchId = (int) $match->getId();
        $meta = ['matchId' => $matchId];
        $scoreCounts = $this->countScoreKeys($matchPronostics, $effectiveById);
        $dayKey = MatchdayKey::fromMatch($match);
        $isKnockout = !$match->isGroupStageMatch();
        $isFinal = $this->isFinalPhase($match);
        $hostMatch = BadgeHostCountries::matchInvolvesHost($match);

        /** @var array<int, true> $usersWithExactOnDay */
        $usersWithExactOnDay = [];

        foreach ($matchPronostics as $pronostic) {
            $user = $pronostic->getJoueur();
            if (!$user instanceof User) {
                continue;
            }

            $pid = (int) $pronostic->getId();
            $effective = $effectiveById[$pid] ?? null;
            $scoreKey = null !== $effective
                ? sprintf('%d-%d', $effective['home'], $effective['away'])
                : sprintf('%d-%d', (int) $pronostic->getScoreDomicile(), (int) $pronostic->getScoreExterieur());

            if (1 === ($scoreCounts[$scoreKey] ?? 0)) {
                $this->grantUser('seul_au_monde', $user, $meta);
            }

            if ($this->isGoodResult($pronostic) && (float) ($pronostic->getCoteCoefficient() ?? 0) >= 3.0) {
                $this->grantUser('cote_malade', $user, $meta);
            }

            if ($this->isExact($pronostic)) {
                if ($hostMatch) {
                    $this->grantUser('pays_hote_prono', $user, $meta);
                }
                if ($isKnockout) {
                    $this->grantUser('knockout_genoux', $user, $meta);
                }
                if ($this->isExactAudacieux($pronostic, $match)) {
                    $this->grantUser('panenka_prono', $user, $meta);
                }

                $storedHome = (int) $pronostic->getScoreDomicile();
                $storedAway = (int) $pronostic->getScoreExterieur();
                $realHome = (int) $match->getScoreDomicile();
                $realAway = (int) $match->getScoreExterieur();
                $wasInverted = ($effective['inverted'] ?? false)
                    || ($storedHome !== $realHome || $storedAway !== $realAway);
                if ($wasInverted && ($effective['inverted'] ?? false)) {
                    $this->grantUser('var_ah_si', $user, $meta);
                }

                if (null !== $dayKey) {
                    $usersWithExactOnDay[(int) $user->getId()] = true;
                }
            }

            if ($isFinal) {
                $this->grantUser('finale_ou_rien', $user, $meta);
            }
        }

        if (null !== $dayKey) {
            foreach (array_keys($usersWithExactOnDay) as $uid) {
                if ($this->countExactPronosForUserOnDay((int) $uid, $dayKey, $allScored) >= 3) {
                    $user = $this->userRepository->find((int) $uid);
                    if ($user instanceof User) {
                        $this->grantUser('hat_trick_dingues', $user, $meta);
                    }
                }
            }
        }

        $this->evaluateFirstExactBadge($matchPronostics);
        $this->evaluateHostTourBadge($matchPronostics, $playerTeamMap, $effectiveById);
    }

    /**
     * @param list<Pronostic>  $matchPronostics
     * @param array<int, int>  $playerTeamMap
     */
    private function evaluateMatchBigBalls(
        GameMatch $match,
        array $matchPronostics,
        array $playerTeamMap,
    ): void {
        $matchId = (int) $match->getId();
        $meta = ['matchId' => $matchId];

        /** @var array<int, array<string, list<Pronostic>>> $byTeamScore */
        $byTeamScore = [];
        foreach ($matchPronostics as $pronostic) {
            if (!$pronostic->isPriseRisque()) {
                continue;
            }
            $playerId = (int) $pronostic->getJoueur()?->getId();
            $teamId = $playerTeamMap[$playerId] ?? null;
            if (null === $teamId) {
                continue;
            }
            $key = sprintf('%d-%d', (int) $pronostic->getScoreDomicile(), (int) $pronostic->getScoreExterieur());
            $byTeamScore[(int) $teamId][$key][] = $pronostic;
        }

        foreach ($byTeamScore as $teamId => $scores) {
            $team = $this->teamRepository->find($teamId);
            if (!$team instanceof Team) {
                continue;
            }

            foreach ($scores as $pronostics) {
                if (count($pronostics) < 2) {
                    continue;
                }

                $this->grantTeam('meme_prono_delire', $team, $meta);

                $succeeded = false;
                $exact = false;
                foreach ($pronostics as $p) {
                    if ($this->isExact($p) || $this->isGoodResult($p)) {
                        $succeeded = true;
                    }
                    if ($this->isExact($p)) {
                        $exact = true;
                    }
                }

                if ($succeeded) {
                    $this->grantTeam('telepathie_vestiaire', $team, $meta);
                    if ($exact) {
                        $this->grantTeam('on_a_ose', $team, $meta);
                    }
                } else {
                    $this->grantTeam('double_mise_honte', $team, $meta);
                }
            }
        }
    }

    /**
     * @param list<Pronostic>  $matchPronostics
     * @param array<int, int>  $playerTeamMap
     */
    private function evaluateMatchJokers(
        GameMatch $match,
        array $matchPronostics,
        array $playerTeamMap,
    ): void {
        $matchId = (int) $match->getId();
        $meta = ['matchId' => $matchId];
        $jokerByTeam = $this->teamJokerUsageRepository->findJokerCodesByTeamForMatch($match);
        $piqueMap = $this->teamJokerUsageRepository->findPiquePointsTargetsByTeamForMatch($match);
        $collecteIds = array_flip($this->teamJokerUsageRepository->findCollecteTeamIdsForMatch($match));

        /** @var array<int, float> $teamMatchPoints */
        $teamMatchPoints = [];
        /** @var array<int, float> $teamRawPoints */
        $teamRawPoints = [];
        foreach ($matchPronostics as $pronostic) {
            $playerId = (int) $pronostic->getJoueur()?->getId();
            $teamId = $playerTeamMap[$playerId] ?? null;
            if (null === $teamId) {
                continue;
            }
            $teamMatchPoints[$teamId] = ($teamMatchPoints[$teamId] ?? 0.0) + $pronostic->getEffectiveTeamPoints();
            $teamRawPoints[$teamId] = ($teamRawPoints[$teamId] ?? 0.0) + (float) ($pronostic->getPoints() ?? 0);
        }

        foreach ($this->teamJokerUsageRepository->findByMatch($match) as $usage) {
            $target = $usage->getTargetTeam();
            if ($target instanceof Team && $this->jokerDefenseService->isUsageNeutralized($usage)) {
                $this->grantTeam('bouclier_anti_chambre', $target, $meta);
                $this->grantTeam('bouclier_humiliation', $target, $meta);
            }
        }

        foreach ($jokerByTeam as $teamId => $code) {
            $team = $this->teamRepository->find((int) $teamId);
            if (!$team instanceof Team) {
                continue;
            }

            if (Joker::CODE_DOUBLE_EQUIPE === $code && ($teamMatchPoints[$teamId] ?? 0) > ($teamRawPoints[$teamId] ?? 0)) {
                $this->grantTeam('double_equipe_peine', $team, $meta);
            }

            if (Joker::CODE_PIQUE_POINTS === $code && isset($piqueMap[(int) $teamId])) {
                $this->grantTeam('pickpocket_points', $team, $meta);
            }

            if (Joker::CODE_COLLECTE_POINTS === $code && isset($collecteIds[(int) $teamId])) {
                $this->grantTeam('collecte_taxes', $team, $meta);
            }

            if (Joker::CODE_INVERSE_SCORE === $code) {
                $this->grantTeam('var_inverse', $team, $meta);
            }

            $net = ($teamMatchPoints[$teamId] ?? 0.0) - ($teamRawPoints[$teamId] ?? 0.0);
            if ($net >= 30) {
                $this->grantTeam('banque_gagne', $team, $meta);
            }
            if ($net < 0) {
                $this->grantTeam('joker_dans_le_mur', $team, $meta);
            }
        }

        foreach ($this->teamRepository->findAll() as $team) {
            $teamId = (int) $team->getId();
            $victimCount = 0;
            foreach ($this->teamJokerUsageRepository->findByMatch($match) as $usage) {
                if ((int) $usage->getTargetTeam()?->getId() === $teamId
                    && JokerDefenseService::isOffensiveAgainstTeam($usage->getJoker()?->getCode())
                    && !$this->jokerDefenseService->isUsageNeutralized($usage)) {
                    ++$victimCount;
                }
            }
            if ($victimCount > 0) {
                $totalVictim = $this->countJokersSuffered($team);
                if ($totalVictim >= 3) {
                    $this->grantTeam('victime_collaterale', $team, $meta);
                }
            }
        }
    }

    /**
     * @param iterable<Pronostic> $allScored
     * @param array<int, int>   $playerTeamMap
     */
    private function evaluateCumulativePlayerStats(iterable $allScored, array $playerTeamMap): void
    {
        /** @var array<int, list<Pronostic>> $byUser */
        $byUser = [];
        foreach ($allScored as $pronostic) {
            $uid = (int) $pronostic->getJoueur()?->getId();
            if ($uid > 0) {
                $byUser[$uid][] = $pronostic;
            }
        }

        foreach ($byUser as $uid => $pronostics) {
            $user = $this->userRepository->find($uid);
            if (!$user instanceof User) {
                continue;
            }

            usort($pronostics, fn (Pronostic $a, Pronostic $b): int => $this->matchSortKey($a) <=> $this->matchSortKey($b));

            $exactCount = 0;
            $zeroStreak = 0;
            $maxZeroStreak = 0;
            $exactStreak = 0;
            $maxExactStreak = 0;
            $zero0Count = 0;
            $highScoreCount = 0;
            $prevDayPoints = null;
            $dayPoints = [];

            foreach ($pronostics as $pronostic) {
                if ($this->isExact($pronostic)) {
                    ++$exactCount;
                    ++$exactStreak;
                    $maxExactStreak = max($maxExactStreak, $exactStreak);
                    $zeroStreak = 0;
                } else {
                    $exactStreak = 0;
                }

                $pts = (int) round((float) ($pronostic->getPoints() ?? 0));
                if (0 === $pts) {
                    ++$zeroStreak;
                    $maxZeroStreak = max($maxZeroStreak, $zeroStreak);
                } else {
                    $zeroStreak = 0;
                }

                if (0 === (int) $pronostic->getScoreDomicile() && 0 === (int) $pronostic->getScoreExterieur() && $this->isExact($pronostic)) {
                    ++$zero0Count;
                }

                $home = (int) $pronostic->getScoreDomicile();
                $away = (int) $pronostic->getScoreExterieur();
                if ($home >= 4 || $away >= 4) {
                    ++$highScoreCount;
                }

                $day = MatchdayKey::fromMatch($pronostic->getMatch() ?? new GameMatch()) ?? '';
                if ('' !== $day) {
                    $dayPoints[$day] = ($dayPoints[$day] ?? 0) + $pts;
                }
            }

            if ($exactCount >= 10) {
                $this->grantUser('chasseur_exact', $user, null);
            }
            if ($exactCount >= 25) {
                $this->grantUser('machine_30', $user, null);
            }
            if ($exactCount >= 50) {
                $this->grantUser('legende_bareme', $user, null);
            }
            if ($maxZeroStreak >= 5) {
                $this->grantUser('cinq_rates', $user, null);
            }
            if ($maxExactStreak >= 3) {
                $this->grantUser('zizou_mode', $user, null);
            }
            if ($zero0Count >= 5) {
                $this->grantUser('clean_sheet_obsession', $user, null);
            }
            if ($this->countPronosWithScore($pronostics, 0, 0) >= 5) {
                $this->grantUser('ame_defenseur', $user, null);
            }
            if ($highScoreCount >= 5) {
                $this->grantUser('ame_attaquant', $user, null);
            }

            $sortedDays = array_keys($dayPoints);
            sort($sortedDays);
            $prev = null;
            $worstDay = null;
            $worstPts = PHP_INT_MAX;
            foreach ($sortedDays as $day) {
                $pts = (int) $dayPoints[$day];
                if ($pts < $worstPts) {
                    $worstPts = $pts;
                    $worstDay = $day;
                }
                if (null !== $prev) {
                    $delta = $pts - (int) $dayPoints[$prev];
                    if ($delta >= 20) {
                        $this->grantUser('remontada', $user, ['day' => $day]);
                    }
                    if ($delta <= -20) {
                        $this->grantUser('effondrement_psg', $user, ['day' => $day]);
                    }
                }
                $prev = $day;
            }
            if (null !== $worstDay && $worstPts <= 0) {
                $this->grantUser('but_contre_camp', $user, ['day' => $worstDay]);
            }
        }
    }

    /**
     * @param iterable<Pronostic> $allScored
     * @param array<int, int>   $playerTeamMap
     */
    private function evaluateCumulativeTeamStats(iterable $allScored, array $playerTeamMap): void
    {
        /** @var array<int, array{tentees: int, reussies: int}> $bbByTeam */
        $bbByTeam = [];
        $bbSeen = [];

        foreach ($allScored as $pronostic) {
            if (!$pronostic->isPriseRisque()) {
                continue;
            }
            $playerId = (int) $pronostic->getJoueur()?->getId();
            $teamId = $playerTeamMap[$playerId] ?? null;
            if (null === $teamId) {
                continue;
            }
            $matchId = (int) $pronostic->getMatch()?->getId();
            $scoreKey = sprintf('%d-%d', (int) $pronostic->getScoreDomicile(), (int) $pronostic->getScoreExterieur());
            $seenKey = $teamId.'|'.$matchId.'|'.$scoreKey;
            if (isset($bbSeen[$seenKey])) {
                continue;
            }
            $bbSeen[$seenKey] = true;

            $bbByTeam[$teamId]['tentees'] = ($bbByTeam[$teamId]['tentees'] ?? 0) + 1;
            if ($this->isExact($pronostic) || $this->isGoodResult($pronostic)) {
                $bbByTeam[$teamId]['reussies'] = ($bbByTeam[$teamId]['reussies'] ?? 0) + 1;
            }
        }

        foreach ($bbByTeam as $teamId => $stats) {
            $team = $this->teamRepository->find((int) $teamId);
            if (!$team instanceof Team) {
                continue;
            }
            if (($stats['reussies'] ?? 0) >= 5) {
                $this->grantTeam('duo_legende', $team, null);
            }
        }

        $this->evaluateBigBallsStreaks($allScored, $playerTeamMap);
    }

    private function evaluateRankingBadges(GameMatch $triggerMatch): void
    {
        $latest = $this->teamRankingSnapshotRepository->findLatestRanking();
        if ([] === $latest) {
            return;
        }

        $vendeeSnapshots = [];
        foreach ($latest as $snapshot) {
            $team = $snapshot->getTeam();
            if (!$team instanceof Team) {
                continue;
            }

            $teamId = (int) $team->getId();
            $history = $this->teamRankingSnapshotRepository->findSnapshotsForTeamOrderedByMatch($team);
            if (count($history) >= 2) {
                $prev = $history[count($history) - 2];
                $delta = (int) $prev->getPosition() - (int) $snapshot->getPosition();
                if ($delta >= 5) {
                    $this->grantTeam('montee_express', $team, ['matchId' => (int) $triggerMatch->getId()]);
                }
                if ($delta <= -5) {
                    $this->grantTeam('chute_libre', $team, ['matchId' => (int) $triggerMatch->getId()]);
                }
            }

            if (count($history) >= 5) {
                $lastFive = \array_slice($history, -5);
                $allTop10 = true;
                foreach ($lastFive as $row) {
                    if ($row->getPosition() > 10) {
                        $allTop10 = false;
                        break;
                    }
                }
                if ($allTop10) {
                    $this->grantTeam('top10_regulier', $team, null);
                }
            }

            $wasFirst = false;
            $nowBad = (int) $snapshot->getPosition() > 5;
            foreach ($history as $i => $row) {
                if ($i < count($history) - 1 && 1 === $row->getPosition()) {
                    $wasFirst = true;
                }
            }
            if ($wasFirst && $nowBad) {
                $this->grantTeam('on_vous_avait_dit', $team, null);
            }

            if (count($history) >= 3) {
                $early = $history[min(2, count($history) - 1)];
                if ($early->getPosition() > 20 && $snapshot->getPosition() <= 10) {
                    $this->grantTeam('outsider_peur', $team, null);
                }
            }

            if ($team->isVendee()) {
                $vendeeSnapshots[] = $snapshot;
            }
        }

        $this->evaluateDepartageBigBalls($latest);

        if ([] !== $vendeeSnapshots) {
            usort($vendeeSnapshots, static function ($a, $b): int {
                return $b->getTotalPoints() <=> $a->getTotalPoints()
                    ?: $b->getScoresExacts() <=> $a->getScoresExacts()
                    ?: $b->getPrisesRisqueReussies() <=> $a->getPrisesRisqueReussies();
            });
            $leader = $vendeeSnapshots[0]->getTeam();
            if ($leader instanceof Team) {
                $this->grantTeam('prefou_or', $leader, null);
            }
        }
    }

    private function evaluateButeurBadges(): void
    {
        foreach ($this->userRepository->findActivePlayersWithButeur() as $user) {
            $buteur = $user->getButeurChoisi();
            if (null === $buteur || $this->butRepository->countForButeur($buteur) <= 0) {
                continue;
            }

            $this->grantUser('buteur_ballon_or', $user, null);
        }
    }

    private function evaluatePronosticParticipationBadges(User $user, GameMatch $match): void
    {
        if ($this->isFinalPhase($match)) {
            $this->grantUser('finale_ou_rien', $user, ['matchId' => (int) $match->getId()]);
        }

        $this->evaluateGroupStageCompletion($user);
        $this->evaluateTeamMatchdayCompletion($user);
    }

    private function evaluateGroupStageCompletion(User $user): void
    {
        $groupMatches = $this->gameMatchRepository->findMatchesForGroupStanding();
        if ([] === $groupMatches) {
            return;
        }

        $indexed = $this->pronosticRepository->findIndexedByPlayerAndMatches($user, $groupMatches);
        if (count($indexed) === count($groupMatches)) {
            $this->grantUser('phase_groupes_survecu', $user, null);
        }
    }

    private function evaluateTeamMatchdayCompletion(User $user): void
    {
        $member = $this->teamMemberRepository->findOneBy(['player' => $user]);
        $team = $member?->getTeam();
        if (!$team instanceof Team) {
            return;
        }

        $members = $team->getMembers();
        if ($members->count() < 2) {
            return;
        }

        $partnerIds = [];
        foreach ($members as $m) {
            $pid = (int) $m->getPlayer()?->getId();
            if ($pid > 0) {
                $partnerIds[] = $pid;
            }
        }

        $played = $this->gameMatchRepository->findMatchesFromDate(new \DateTimeImmutable('2000-01-01'));
        $played = array_filter($played, static fn (GameMatch $m): bool => null !== $m->getScoreDomicile() && null !== $m->getScoreExterieur());

        /** @var array<string, list<GameMatch>> $byDay */
        $byDay = [];
        foreach ($played as $m) {
            $day = MatchdayKey::fromMatch($m);
            if (null !== $day) {
                $byDay[$day][] = $m;
            }
        }

        foreach ($byDay as $dayMatches) {
            $allPartnersComplete = true;
            foreach ($partnerIds as $pid) {
                $partner = $this->userRepository->find($pid);
                if (!$partner instanceof User) {
                    $allPartnersComplete = false;
                    break;
                }
                $indexed = $this->pronosticRepository->findIndexedByPlayerAndMatches($partner, $dayMatches);
                if (count($indexed) !== count($dayMatches)) {
                    $allPartnersComplete = false;
                    break;
                }
            }
            if ($allPartnersComplete) {
                $this->grantTeam('prono_ensemble_dispute', $team, ['day' => $day]);
                break;
            }
        }
    }

    /**
     * @param list<Pronostic> $matchPronostics
     */
    private function evaluateFirstExactBadge(array $matchPronostics): void
    {
        $all = $this->pronosticRepository->findScoredPronosticsWithTeamMembers();
        $first = null;
        foreach ($all as $pronostic) {
            if (!$this->isExact($pronostic)) {
                continue;
            }
            $key = $this->matchSortKey($pronostic);
            if (null === $first || $key < $first['key']) {
                $first = ['key' => $key, 'user' => $pronostic->getJoueur()];
            }
        }
        if (null !== $first && $first['user'] instanceof User) {
            $this->grantUser('premier_exact', $first['user'], null);
        }
    }

    /**
     * @param list<Pronostic>  $matchPronostics
     * @param array<int, int>  $playerTeamMap
     * @param array<int, array{home: int, away: int, inverted: bool}> $effectiveById
     */
    private function evaluateHostTourBadge(array $matchPronostics, array $playerTeamMap, array $effectiveById): void
    {
        /** @var array<int, array<string, true>> $hostsByUser */
        $hostsByUser = [];
        foreach ($matchPronostics as $pronostic) {
            if (!$this->isExact($pronostic)) {
                continue;
            }
            $match = $pronostic->getMatch();
            if (!$match instanceof GameMatch) {
                continue;
            }
            foreach ([$match->getPaysDomicile(), $match->getPaysExterieur()] as $country) {
                $hostKey = BadgeHostCountries::hostKey($country);
                if (null === $hostKey) {
                    continue;
                }
                $uid = (int) $pronostic->getJoueur()?->getId();
                $hostsByUser[$uid][$hostKey] = true;
            }
        }

        foreach ($hostsByUser as $uid => $hosts) {
            if (count($hosts) >= 3) {
                $user = $this->userRepository->find((int) $uid);
                if ($user instanceof User) {
                    $this->grantUser('tour_3_hotes', $user, null);
                }
            }
        }

        $all = $this->pronosticRepository->findScoredPronosticsWithTeamMembers();
        /** @var array<int, array<string, true>> $allHosts */
        $allHosts = [];
        foreach ($all as $pronostic) {
            if (!$this->isExact($pronostic)) {
                continue;
            }
            $match = $pronostic->getMatch();
            if (!$match instanceof GameMatch || !BadgeHostCountries::matchInvolvesHost($match)) {
                continue;
            }
            foreach ([$match->getPaysDomicile(), $match->getPaysExterieur()] as $country) {
                $hostKey = BadgeHostCountries::hostKey($country);
                if (null !== $hostKey) {
                    $uid = (int) $pronostic->getJoueur()?->getId();
                    $allHosts[$uid][$hostKey] = true;
                }
            }
        }
        foreach ($allHosts as $uid => $hosts) {
            if (count($hosts) >= 3) {
                $user = $this->userRepository->find((int) $uid);
                if ($user instanceof User) {
                    $this->grantUser('tour_3_hotes', $user, null);
                }
            }
        }
    }

    /**
     * @param iterable<Pronostic> $allScored
     * @param array<int, int>   $playerTeamMap
     */
    private function evaluateBigBallsStreaks(iterable $allScored, array $playerTeamMap): void
    {
        /** @var array<int, list<array{matchId: int, ok: bool}>> $eventsByTeam */
        $eventsByTeam = [];
        $processed = [];

        foreach ($allScored as $pronostic) {
            if (!$pronostic->isPriseRisque()) {
                continue;
            }
            $playerId = (int) $pronostic->getJoueur()?->getId();
            $teamId = $playerTeamMap[$playerId] ?? null;
            $matchId = (int) $pronostic->getMatch()?->getId();
            if (null === $teamId || $matchId <= 0) {
                continue;
            }
            $scoreKey = sprintf('%d-%d', (int) $pronostic->getScoreDomicile(), (int) $pronostic->getScoreExterieur());
            $key = $teamId.'|'.$matchId.'|'.$scoreKey;
            if (isset($processed[$key])) {
                continue;
            }
            $processed[$key] = true;

            $ok = $this->isExact($pronostic) || $this->isGoodResult($pronostic);
            $eventsByTeam[$teamId][] = ['matchId' => $matchId, 'ok' => $ok];
        }

        foreach ($eventsByTeam as $teamId => $events) {
            usort($events, static fn (array $a, array $b): int => $a['matchId'] <=> $b['matchId']);
            $streak = 0;
            foreach ($events as $event) {
                if ($event['ok']) {
                    ++$streak;
                    if ($streak >= 3) {
                        $team = $this->teamRepository->find((int) $teamId);
                        if ($team instanceof Team) {
                            $this->grantTeam('trois_fois_affilee', $team, null);
                        }
                        break;
                    }
                } else {
                    $streak = 0;
                }
            }
        }
    }

    /**
     * @param list<Pronostic> $pronostics
     * @param array<int, array{home: int, away: int, inverted: bool}> $effectiveById
     *
     * @return array<string, int>
     */
    private function countScoreKeys(array $pronostics, array $effectiveById): array
    {
        $counts = [];
        foreach ($pronostics as $pronostic) {
            $pid = (int) $pronostic->getId();
            $effective = $effectiveById[$pid] ?? null;
            $key = null !== $effective
                ? sprintf('%d-%d', $effective['home'], $effective['away'])
                : sprintf('%d-%d', (int) $pronostic->getScoreDomicile(), (int) $pronostic->getScoreExterieur());
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    private function countExactPronosForUserOnDay(int $userId, string $dayKey, iterable $allScored): int
    {
        $count = 0;
        foreach ($allScored as $pronostic) {
            if ((int) $pronostic->getJoueur()?->getId() !== $userId || !$this->isExact($pronostic)) {
                continue;
            }
            $match = $pronostic->getMatch();
            if (!$match instanceof GameMatch) {
                continue;
            }
            if (MatchdayKey::fromMatch($match) === $dayKey) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param list<\App\Entity\TeamRankingSnapshot> $snapshots
     */
    private function evaluateDepartageBigBalls(array $snapshots): void
    {
        if (count($snapshots) < 2) {
            return;
        }

        $withBb = $this->rankSnapshotsByPosition($snapshots, true);
        $withoutBb = $this->rankSnapshotsByPosition($snapshots, false);

        foreach ($snapshots as $snapshot) {
            $team = $snapshot->getTeam();
            if (!$team instanceof Team) {
                continue;
            }
            $teamId = (int) $team->getId();
            $posWith = $withBb[$teamId] ?? null;
            $posWithout = $withoutBb[$teamId] ?? null;
            if (null === $posWith || null === $posWithout) {
                continue;
            }
            if ($posWith < $posWithout) {
                $this->grantTeam('departage_bigballs', $team, null);
            }
        }
    }

    /**
     * @param list<\App\Entity\TeamRankingSnapshot> $snapshots
     *
     * @return array<int, int> teamId => position (1-based)
     */
    private function rankSnapshotsByPosition(array $snapshots, bool $includeBigBallsTieBreak): array
    {
        $rows = $snapshots;
        usort(
            $rows,
            static function ($a, $b) use ($includeBigBallsTieBreak): int {
                $cmp = $b->getTotalPoints() <=> $a->getTotalPoints()
                    ?: $b->getScoresExacts() <=> $a->getScoresExacts()
                    ?: $b->getBonsResultats() <=> $a->getBonsResultats();
                if ($includeBigBallsTieBreak) {
                    $cmp = $cmp ?: $b->getPrisesRisque() <=> $a->getPrisesRisque();
                }

                return $cmp ?: strcmp((string) $a->getTeam()?->getName(), (string) $b->getTeam()?->getName());
            },
        );

        $positions = [];
        foreach ($rows as $index => $row) {
            $teamId = (int) $row->getTeam()?->getId();
            if ($teamId > 0) {
                $positions[$teamId] = $index + 1;
            }
        }

        return $positions;
    }

    private function countJokersSuffered(Team $team): int
    {
        $count = 0;
        foreach ($this->teamJokerUsageRepository->findByTeamOrdered($team) as $usage) {
            if ((int) $usage->getTargetTeam()?->getId() === (int) $team->getId()
                && JokerDefenseService::isOffensiveAgainstTeam($usage->getJoker()?->getCode())
                && !$this->jokerDefenseService->isUsageNeutralized($usage)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param list<Pronostic> $pronostics
     */
    private function countPronosWithScore(array $pronostics, int $home, int $away): int
    {
        $n = 0;
        foreach ($pronostics as $p) {
            if ((int) $p->getScoreDomicile() === $home && (int) $p->getScoreExterieur() === $away) {
                ++$n;
            }
        }

        return $n;
    }

    private function isExact(Pronostic $pronostic): bool
    {
        $base = $pronostic->getPointsBase();

        return null !== $base && $base >= self::EXACT_BASE;
    }

    private function isGoodResult(Pronostic $pronostic): bool
    {
        $base = $pronostic->getPointsBase();

        return null !== $base && $base >= self::GOOD_BASE && $base < self::EXACT_BASE;
    }

    private function isExactAudacieux(Pronostic $pronostic, GameMatch $match): bool
    {
        if (!$this->isExact($pronostic)) {
            return false;
        }

        $coeff = (float) ($pronostic->getCoteCoefficient() ?? 0);
        $max = (float) ($match->getCoteMax() ?? 0);

        return $max > 0 && $coeff >= $max;
    }

    private function isFinalPhase(GameMatch $match): bool
    {
        $phase = mb_strtolower(trim((string) $match->getPhase()));

        return str_contains($phase, 'final') && !str_contains($phase, 'semi');
    }

    private function matchSortKey(Pronostic $pronostic): int
    {
        $match = $pronostic->getMatch();
        if (!$match instanceof GameMatch) {
            return 0;
        }

        return (int) ($match->getDateHeure()?->getTimestamp() ?? 0) * 1000 + (int) $match->getId();
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function grantUser(string $code, User $user, ?array $metadata): void
    {
        $badge = $this->badge($code);
        if ($badge instanceof BadgeDefinition) {
            $this->awardGranter->grantToUser($badge, $user, $metadata);
        }
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    private function grantTeam(string $code, Team $team, ?array $metadata): void
    {
        $badge = $this->badge($code);
        if ($badge instanceof BadgeDefinition) {
            $this->awardGranter->grantToTeam($badge, $team, $metadata);
        }
    }

    private function badge(string $code): ?BadgeDefinition
    {
        if (null === $this->badgesByCode) {
            $this->badgesByCode = $this->badgeDefinitionRepository->findActiveIndexedByCode();
        }

        return $this->badgesByCode[$code] ?? null;
    }
}
