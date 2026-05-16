<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Entity\User;
use App\Repository\JokerRepository;
use App\Repository\TeamJokerUsageRepository;
use App\Repository\TeamMemberRepository;
use Doctrine\ORM\EntityManagerInterface;

final class TeamJokerService
{
    public function __construct(
        private readonly JokerRepository $jokerRepository,
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly MatchStatusResolver $matchStatusResolver,
        private readonly PronosticScoringService $pronosticScoringService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<array{
     *     joker: Joker,
     *     usage: TeamJokerUsage|null,
     *     status: string,
     *     status_label: string
     * }>
     */
    public function buildOverviewForTeam(Team $team): array
    {
        $usagesByJokerId = [];
        foreach ($this->teamJokerUsageRepository->findByTeamOrdered($team) as $usage) {
            $jokerId = $usage->getJoker()?->getId();
            if (null !== $jokerId) {
                $usagesByJokerId[(int) $jokerId] = $usage;
            }
        }

        $pendingUsage = $this->findPendingUsageForTeam($team);
        $pendingMatchId = $pendingUsage?->getMatch()?->getId();

        $rows = [];
        foreach ($this->jokerRepository->findAllOrdered() as $joker) {
            $jokerId = (int) $joker->getId();
            $usage = $usagesByJokerId[$jokerId] ?? null;

            if ($usage instanceof TeamJokerUsage) {
                $status = 'used';
                $statusLabel = 'Utilisé';
            } elseif (!$joker->isActive()) {
                $status = 'inactive';
                $statusLabel = 'Indisponible';
            } elseif (null !== $pendingUsage) {
                $status = 'blocked';
                $statusLabel = 'Un autre joker est déjà en cours';
            } else {
                $status = 'available';
                $statusLabel = 'Disponible';
            }

            $rows[] = [
                'joker' => $joker,
                'usage' => $usage,
                'status' => $status,
                'status_label' => $statusLabel,
                'blocked_match_id' => 'blocked' === $status ? $pendingMatchId : null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array{name: string, image: ?string, code: string}>
     */
    public function buildUsageSummaryByMatchIdForTeam(Team $team): array
    {
        $map = [];
        foreach ($this->teamJokerUsageRepository->findByTeamOrdered($team) as $usage) {
            $matchId = $usage->getMatch()?->getId();
            $joker = $usage->getJoker();
            if (null === $matchId || !$joker instanceof Joker) {
                continue;
            }

            $map[(int) $matchId] = [
                'name' => (string) $joker->getName(),
                'image' => $joker->getImage(),
                'code' => (string) $joker->getCode(),
            ];
        }

        return $map;
    }

    /**
     * @return array{
     *     can_manage: bool,
     *     reason: ?string,
     *     active_on_match: ?array{id: int, name: string, image: ?string, code: string},
     *     pending_elsewhere: ?array{match_id: int, match_label: string, joker_name: string},
     *     jokers: list<array{
     *         id: int,
     *         code: string,
     *         name: string,
     *         description: ?string,
     *         image: ?string,
     *         can_play: bool,
     *         disabled_reason: ?string,
     *         already_used: bool
     *     }>
     * }
     */
    public function buildMatchPickerState(User $user, GameMatch $match): array
    {
        $team = $this->teamMemberRepository->findOneBy(['player' => $user])?->getTeam();
        if (!$team instanceof Team) {
            return [
                'can_manage' => false,
                'reason' => 'Rejoignez une équipe pour utiliser les jokers.',
                'active_on_match' => null,
                'pending_elsewhere' => null,
                'jokers' => [],
            ];
        }

        if (!$user->isCotisationPayee()) {
            return [
                'can_manage' => false,
                'reason' => 'Réglez votre cotisation pour utiliser les jokers.',
                'active_on_match' => null,
                'pending_elsewhere' => null,
                'jokers' => [],
            ];
        }

        $usageOnMatch = $this->teamJokerUsageRepository->findOneByTeamAndMatch($team, $match);
        $activeOnMatch = null;
        if ($usageOnMatch instanceof TeamJokerUsage) {
            $joker = $usageOnMatch->getJoker();
            $activeOnMatch = [
                'id' => (int) $joker?->getId(),
                'name' => (string) $joker?->getName(),
                'image' => $joker?->getImage(),
                'code' => (string) $joker?->getCode(),
                'can_remove' => $this->isMatchOpenForJoker($match),
            ];
        }

        $pendingUsage = $this->findPendingUsageForTeam($team);
        $pendingElsewhere = null;
        if ($pendingUsage instanceof TeamJokerUsage) {
            $pendingMatch = $pendingUsage->getMatch();
            $pendingMatchId = $pendingMatch?->getId();
            if (null !== $pendingMatchId && (int) $pendingMatchId !== (int) $match->getId()) {
                $pendingElsewhere = [
                    'match_id' => (int) $pendingMatchId,
                    'match_label' => $this->formatMatchLabel($pendingMatch),
                    'joker_name' => (string) $pendingUsage->getJoker()?->getName(),
                ];
            }
        }

        $canPlaceOnThisMatch = $this->canPlaceOnMatch($team, $match);
        $usagesByJokerId = [];
        foreach ($this->teamJokerUsageRepository->findByTeamOrdered($team) as $usage) {
            $jid = $usage->getJoker()?->getId();
            if (null !== $jid) {
                $usagesByJokerId[(int) $jid] = $usage;
            }
        }

        $jokers = [];
        foreach ($this->jokerRepository->findAllOrdered() as $joker) {
            if (!$joker->isActive()) {
                continue;
            }

            $jokerId = (int) $joker->getId();
            $alreadyUsed = isset($usagesByJokerId[$jokerId]);
            $canPlay = false;
            $disabledReason = null;

            if ($activeOnMatch !== null) {
                $disabledReason = 'Un joker est déjà posé sur ce match.';
            } elseif ($alreadyUsed) {
                $disabledReason = 'Ce joker a déjà été utilisé par votre équipe.';
            } elseif (null !== $pendingElsewhere) {
                $disabledReason = sprintf(
                    'Joker « %s » en cours sur %s.',
                    $pendingElsewhere['joker_name'],
                    $pendingElsewhere['match_label'],
                );
            } elseif (!$canPlaceOnThisMatch['allowed']) {
                $disabledReason = $canPlaceOnThisMatch['reason'];
            } else {
                $canPlay = true;
            }

            $jokers[] = [
                'id' => $jokerId,
                'code' => (string) $joker->getCode(),
                'name' => (string) $joker->getName(),
                'description' => $joker->getDescription(),
                'image' => $joker->getImage(),
                'can_play' => $canPlay,
                'disabled_reason' => $disabledReason,
                'already_used' => $alreadyUsed,
            ];
        }

        return [
            'can_manage' => true,
            'reason' => null,
            'active_on_match' => $activeOnMatch,
            'pending_elsewhere' => $pendingElsewhere,
            'jokers' => $jokers,
        ];
    }

    /**
     * @return array{allowed: bool, reason: ?string}
     */
    public function canPlaceOnMatch(Team $team, GameMatch $match): array
    {
        if (!$this->isMatchOpenForJoker($match)) {
            return [
                'allowed' => false,
                'reason' => 'Les jokers ne peuvent être posés que sur un match à venir (avant le coup d\'envoi).',
            ];
        }

        if ($this->teamJokerUsageRepository->findOneByTeamAndMatch($team, $match) instanceof TeamJokerUsage) {
            return [
                'allowed' => false,
                'reason' => 'Votre équipe a déjà un joker sur ce match.',
            ];
        }

        $pending = $this->findPendingUsageForTeam($team);
        if ($pending instanceof TeamJokerUsage) {
            return [
                'allowed' => false,
                'reason' => sprintf(
                    'Un joker est déjà en cours sur %s.',
                    $this->formatMatchLabel($pending->getMatch()),
                ),
            ];
        }

        return ['allowed' => true, 'reason' => null];
    }

    public function placeJoker(User $user, GameMatch $match, Joker $joker): void
    {
        $teamMember = $this->teamMemberRepository->findOneBy(['player' => $user]);
        $team = $teamMember?->getTeam();
        if (!$team instanceof Team) {
            throw new \InvalidArgumentException('Vous devez faire partie d\'une équipe.');
        }

        if (!$user->isCotisationPayee()) {
            throw new \InvalidArgumentException('Réglez votre cotisation pour utiliser les jokers.');
        }

        if (!$joker->isActive()) {
            throw new \InvalidArgumentException('Ce joker n\'est pas disponible.');
        }

        $canPlace = $this->canPlaceOnMatch($team, $match);
        if (!$canPlace['allowed']) {
            throw new \InvalidArgumentException((string) $canPlace['reason']);
        }

        if ($this->teamJokerUsageRepository->findOneByTeamAndJoker($team, $joker) instanceof TeamJokerUsage) {
            throw new \InvalidArgumentException('Votre équipe a déjà utilisé ce joker.');
        }

        $usage = (new TeamJokerUsage())
            ->setTeam($team)
            ->setJoker($joker)
            ->setMatch($match);

        $this->entityManager->persist($usage);
        $this->entityManager->flush();

        if (null !== $match->getScoreDomicile() && null !== $match->getScoreExterieur()) {
            $this->pronosticScoringService->rescoreForMatch($match);
        }
    }

    public function removeJokerFromMatch(User $user, GameMatch $match): void
    {
        $teamMember = $this->teamMemberRepository->findOneBy(['player' => $user]);
        $team = $teamMember?->getTeam();
        if (!$team instanceof Team) {
            throw new \InvalidArgumentException('Vous devez faire partie d\'une équipe.');
        }

        if (!$user->isCotisationPayee()) {
            throw new \InvalidArgumentException('Réglez votre cotisation pour utiliser les jokers.');
        }

        if (!$this->isMatchOpenForJoker($match)) {
            throw new \InvalidArgumentException('Ce joker ne peut plus être retiré (match déjà commencé ou terminé).');
        }

        $usage = $this->teamJokerUsageRepository->findOneByTeamAndMatch($team, $match);
        if (!$usage instanceof TeamJokerUsage) {
            throw new \InvalidArgumentException('Aucun joker posé sur ce match.');
        }

        $this->entityManager->remove($usage);
        $this->entityManager->flush();

        if (null !== $match->getScoreDomicile() && null !== $match->getScoreExterieur()) {
            $this->pronosticScoringService->rescoreForMatch($match);
        }
    }

    /**
     * @return array<int, array{name: string, image: ?string, code: string}>
     */
    public function buildActiveJokersByTeamIdForMatch(GameMatch $match): array
    {
        $map = [];
        foreach ($this->teamJokerUsageRepository->findByMatch($match) as $usage) {
            $teamId = $usage->getTeam()?->getId();
            $joker = $usage->getJoker();
            if (null === $teamId || !$joker instanceof Joker) {
                continue;
            }

            $map[(int) $teamId] = [
                'name' => (string) $joker->getName(),
                'image' => $joker->getImage(),
                'code' => (string) $joker->getCode(),
            ];
        }

        return $map;
    }

    public function findPendingUsageForTeam(Team $team): ?TeamJokerUsage
    {
        foreach ($this->teamJokerUsageRepository->findByTeamOrdered($team) as $usage) {
            $match = $usage->getMatch();
            if ($match instanceof GameMatch && !$this->matchStatusResolver->isMatchFinished($match)) {
                return $usage;
            }
        }

        return null;
    }

    public function isMatchOpenForJoker(GameMatch $match): bool
    {
        return $this->matchStatusResolver->canEditBeforeKickoff($match);
    }

    private function formatMatchLabel(?GameMatch $match): string
    {
        if (!$match instanceof GameMatch) {
            return 'un autre match';
        }

        $home = $match->getPaysDomicile()?->getNom() ?? '?';
        $away = $match->getPaysExterieur()?->getNom() ?? '?';

        return sprintf('%s — %s', $home, $away);
    }
}
