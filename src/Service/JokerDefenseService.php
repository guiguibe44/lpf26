<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Repository\TeamJokerUsageRepository;
use App\Repository\TeamRepository;

/**
 * Protection contre les jokers offensifs ciblés : bouclier (journée) et équipe favorite (poules).
 */
final class JokerDefenseService
{
    public function __construct(
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly TeamRepository $teamRepository,
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
        $protected = $this->teamJokerUsageRepository->findProtectedTeamIdsForMatchdayOfMatch($match);

        foreach ($this->teamRepository->findTeamIdsWithFavoriteCountryInGroupMatch($match) as $teamId) {
            $protected[$teamId] = true;
        }

        return $protected;
    }

    public function isTeamProtectedOnMatch(Team $team, GameMatch $match): bool
    {
        $teamId = $team->getId();
        if (null === $teamId) {
            return false;
        }

        return isset($this->getProtectedTeamIdsForMatch($match)[(int) $teamId]);
    }

    public function isTeamProtectedByFavoriteOnGroupMatch(Team $team, GameMatch $match): bool
    {
        if (null === $match->getGroupStandingLetter()) {
            return false;
        }

        $favorite = $team->getFavoriteCountry();
        if (!$favorite instanceof Country || null === $favorite->getId()) {
            return false;
        }

        $favoriteId = (int) $favorite->getId();
        foreach ([$match->getPaysDomicile(), $match->getPaysExterieur()] as $country) {
            if ($country instanceof Country && (int) $country->getId() === $favoriteId) {
                return true;
            }
        }

        return false;
    }

    public function teamHasBouclierOnMatchday(Team $team, GameMatch $match): bool
    {
        $teamId = $team->getId();
        if (null === $teamId) {
            return false;
        }

        return isset($this->teamJokerUsageRepository->findProtectedTeamIdsForMatchdayOfMatch($match)[(int) $teamId]);
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
}
