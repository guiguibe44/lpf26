<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Repository\TeamJokerUsageRepository;

/**
 * Joker « bouclier » : protège l'équipe sur toute la journée calendaire du match choisi.
 * Les jokers adverses qui ciblent cette équipe sont consommés sans effet.
 */
final class JokerDefenseService
{
    public function __construct(
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
    ) {
    }

    public static function isOffensiveAgainstTeam(?string $jokerCode): bool
    {
        return \in_array($jokerCode, [
            Joker::CODE_PIQUE_POINTS,
            Joker::CODE_INVERSE_BUTEUR,
            Joker::CODE_INVERSE_SCORE,
        ], true);
    }

    /**
     * @return array<int, true>
     */
    public function getProtectedTeamIdsForMatch(GameMatch $match): array
    {
        return $this->teamJokerUsageRepository->findProtectedTeamIdsForMatchdayOfMatch($match);
    }

    public function isTeamProtectedOnMatch(Team $team, GameMatch $match): bool
    {
        $teamId = $team->getId();
        if (null === $teamId) {
            return false;
        }

        return isset($this->getProtectedTeamIdsForMatch($match)[(int) $teamId]);
    }

    public function isUsageNeutralized(TeamJokerUsage $usage): bool
    {
        $code = $usage->getJoker()?->getCode();
        if (!self::isOffensiveAgainstTeam($code)) {
            return false;
        }

        $target = $usage->getTargetTeam();
        $match = $usage->getMatch();
        if (!$target instanceof Team || !$match instanceof GameMatch) {
            return false;
        }

        return $this->isTeamProtectedOnMatch($target, $match);
    }

    public function wouldOffensiveJokerBeNeutralized(Team $target, GameMatch $match, Joker $joker): bool
    {
        if (!self::isOffensiveAgainstTeam($joker->getCode())) {
            return false;
        }

        return $this->isTeamProtectedOnMatch($target, $match);
    }

    public function teamHasBouclierOnMatchday(Team $team, GameMatch $match): bool
    {
        return $this->isTeamProtectedOnMatch($team, $match);
    }
}
