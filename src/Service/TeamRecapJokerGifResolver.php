<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Repository\TeamJokerUsageRepository;
use App\TeamRecap\TeamRecapGifSlot;

/**
 * Choisit un seul GIF joker (tirage aléatoire dans le slot utile / pas utile) pour le récap d’équipe.
 */
final class TeamRecapJokerGifResolver
{
    private const PRIORITY_PLACED = 0;

    private const PRIORITY_SUFFERED = 1;

    public function __construct(
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly JokerDefenseService $jokerDefenseService,
        private readonly TeamRecapGifPicker $teamRecapGifPicker,
    ) {
    }

    /**
     * @param list<GameMatch> $matches
     * @param array<int, int> $teamPointsByMatchId
     */
    public function resolveAbsoluteUrl(Team $team, array $matches, array $teamPointsByMatchId): ?string
    {
        $teamId = (int) $team->getId();
        if ($teamId <= 0 || [] === $matches) {
            return null;
        }

        $events = $this->collectEvents($team, $teamId, $matches, $teamPointsByMatchId);
        if ([] === $events) {
            return null;
        }

        usort(
            $events,
            static function (array $a, array $b): int {
                $priority = $a['priority'] <=> $b['priority'];
                if (0 !== $priority) {
                    return $priority;
                }

                return $b['match_at'] <=> $a['match_at'];
            },
        );

        foreach ($events as $event) {
            $slot = $event['useful']
                ? TeamRecapGifSlot::jokerUseful($event['code'])
                : TeamRecapGifSlot::jokerNotUseful($event['code']);

            $url = $this->teamRecapGifPicker->pickRandomAbsoluteUrl($slot);
            if (null !== $url) {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param list<GameMatch> $matches
     * @param array<int, int> $teamPointsByMatchId
     *
     * @return list<array{code: string, useful: bool, match_at: int, priority: int}>
     */
    private function collectEvents(Team $team, int $teamId, array $matches, array $teamPointsByMatchId): array
    {
        $events = [];

        foreach ($matches as $match) {
            $matchAt = $match->getDateHeure()?->getTimestamp() ?? 0;
            $mid = (int) $match->getId();
            $teamPoints = (int) ($teamPointsByMatchId[$mid] ?? 0);

            foreach ($this->teamJokerUsageRepository->findByMatch($match) as $usage) {
                if (!$usage instanceof TeamJokerUsage) {
                    continue;
                }

                $code = (string) ($usage->getJoker()?->getCode() ?? '');
                if ('' === $code) {
                    continue;
                }

                $ownerId = (int) ($usage->getTeam()?->getId() ?? 0);

                if ($ownerId === $teamId) {
                    $events[] = [
                        'code' => $code,
                        'useful' => $this->isPlacedJokerUseful($usage, $teamPoints),
                        'match_at' => $matchAt,
                        'priority' => self::PRIORITY_PLACED,
                    ];
                    continue;
                }

                $targetId = (int) ($usage->getTargetTeam()?->getId() ?? 0);
                if ($targetId === $teamId && JokerDefenseService::isOffensiveAgainstTeam($code)) {
                    $blocked = $this->jokerDefenseService->isTeamProtectedOnMatch($team, $match);
                    $events[] = [
                        'code' => $code,
                        'useful' => $this->isSufferedOffensiveUseful($blocked),
                        'match_at' => $matchAt,
                        'priority' => self::PRIORITY_SUFFERED,
                    ];
                }
            }

            if ($this->jokerDefenseService->isTeamProtectedByFavoriteOnGroupMatch($team, $match)) {
                $events[] = [
                    'code' => Joker::CODE_EQUIPE_FAVORITE,
                    'useful' => true,
                    'match_at' => $matchAt,
                    'priority' => self::PRIORITY_SUFFERED,
                ];
            }
        }

        return $events;
    }

    private function isPlacedJokerUseful(TeamJokerUsage $usage, int $teamPointsOnMatch): bool
    {
        if ($this->jokerDefenseService->isUsageNeutralized($usage)) {
            return false;
        }

        $code = $usage->getJoker()?->getCode();
        if (\in_array($code, [Joker::CODE_BOUCLIER, Joker::CODE_EQUIPE_FAVORITE], true)) {
            return true;
        }

        return $teamPointsOnMatch > 0;
    }

    private function isSufferedOffensiveUseful(bool $blocked): bool
    {
        return $blocked;
    }
}
