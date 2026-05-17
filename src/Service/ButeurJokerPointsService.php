<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Buteur;
use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Entity\Team;
use App\Entity\TeamJokerUsage;
use App\Repository\ButRepository;
use App\Repository\TeamJokerUsageRepository;

/**
 * Jokers buteur : double (×2) et inversion (points négatifs pour l'équipe ciblée).
 */
final class ButeurJokerPointsService
{
    public function __construct(
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly ButRepository $butRepository,
    ) {
    }

    /**
     * @return list<int>
     */
    public function getButeurCountryIdsForTeam(Team $team): array
    {
        $ids = [];
        foreach ($team->getMembers() as $member) {
            $countryId = $member->getPlayer()?->getButeurChoisi()?->getPays()?->getId();
            if (null !== $countryId) {
                $ids[(int) $countryId] = (int) $countryId;
            }
        }

        return array_values($ids);
    }

    /**
     * @return list<string>
     */
    public function getButeurCountryNamesForTeam(Team $team): array
    {
        $names = [];
        foreach ($team->getMembers() as $member) {
            $country = $member->getPlayer()?->getButeurChoisi()?->getPays();
            if ($country instanceof Country) {
                $name = (string) $country->getNom();
                if ('' !== $name) {
                    $names[$name] = $name;
                }
            }
        }

        sort($names);

        return array_values($names);
    }

    /**
     * @return list<int>
     */
    public function getMatchCountryIds(GameMatch $match): array
    {
        $ids = [];
        foreach ([$match->getPaysDomicile(), $match->getPaysExterieur()] as $country) {
            if ($country instanceof Country && null !== $country->getId()) {
                $ids[] = (int) $country->getId();
            }
        }

        return $ids;
    }

    public function isMatchEligibleForTeamButeurCountries(Team $team, GameMatch $match): bool
    {
        $buteurCountryIds = $this->getButeurCountryIdsForTeam($team);
        if ([] === $buteurCountryIds) {
            return false;
        }

        $matchCountryIds = $this->getMatchCountryIds($match);
        if ([] === $matchCountryIds) {
            return false;
        }

        foreach ($buteurCountryIds as $countryId) {
            if (\in_array($countryId, $matchCountryIds, true)) {
                return true;
            }
        }

        return false;
    }

    public function isMatchEligibleForDoubleButeurJoker(Team $team, GameMatch $match): bool
    {
        return $this->isMatchEligibleForTeamButeurCountries($team, $match);
    }

    public function teamHasDoubleButeurJokerOnMatch(Team $team, GameMatch $match): bool
    {
        $usage = $this->teamJokerUsageRepository->findOneByTeamAndMatch($team, $match);
        if (!$usage instanceof TeamJokerUsage) {
            return false;
        }

        return Joker::CODE_DOUBLE_BUTEUR === $usage->getJoker()?->getCode();
    }

    public function teamIsTargetOfInvertButeurJokerOnMatch(Team $team, GameMatch $match): bool
    {
        return $this->teamJokerUsageRepository->teamIsTargetOfInvertButeurOnMatch($team, $match);
    }

    /**
     * @return list<int>
     */
    public function findDoubleButeurMatchIdsForTeam(Team $team): array
    {
        $matchIds = [];
        foreach ($this->teamJokerUsageRepository->findByTeamOrdered($team) as $usage) {
            if (Joker::CODE_DOUBLE_BUTEUR !== $usage->getJoker()?->getCode()) {
                continue;
            }

            $matchId = $usage->getMatch()?->getId();
            if (null !== $matchId) {
                $matchIds[] = (int) $matchId;
            }
        }

        return $matchIds;
    }

    public function sumEffectivePointsForButeur(Team $team, Buteur $buteur): float
    {
        $doubleMatchIds = array_fill_keys($this->findDoubleButeurMatchIdsForTeam($team), true);
        $invertMatchIds = array_fill_keys($this->teamJokerUsageRepository->findInvertButeurMatchIdsForTargetTeam($team), true);

        if ([] === $doubleMatchIds && [] === $invertMatchIds) {
            return (float) $this->butRepository->sumPointsAttribuesForButeur($buteur);
        }

        $total = 0.0;
        foreach ($this->butRepository->findForButeurOrderedByMatch($buteur) as $but) {
            $points = $but->getPointsAttribues();
            $matchId = $but->getMatchRef()?->getId();
            if (null === $matchId) {
                $total += $points;

                continue;
            }

            $matchKey = (int) $matchId;
            if (isset($doubleMatchIds[$matchKey])) {
                $points *= 2;
            }

            if (isset($invertMatchIds[$matchKey])) {
                $points = -$points;
            }

            $total += $points;
        }

        return $total;
    }
}
