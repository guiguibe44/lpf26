<?php

declare(strict_types=1);

namespace App\Service;

use App\Data\WorldCup2026Groups;
use App\Entity\Country;
use App\Entity\GameMatch;
use App\Repository\CountryRepository;
use App\Repository\GameMatchRepository;

/**
 * Construit les classements de phase de groupes à partir des matchs (phase « Group X »),
 * du champ pays.groupe (admin) et de la grille CDM 2026 (noms API / variantes).
 */
final class GroupStandingsBuilder
{
    public function __construct(
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly CountryRepository $countryRepository,
    ) {
    }

    /**
     * @return array<string, list<array{
     *     country: Country,
     *     joues: int,
     *     victoires: int,
     *     nuls: int,
     *     defaites: int,
     *     bp: int,
     *     bc: int,
     *     diff: int,
     *     points: int
     * }>>
     */
    public function build(): array
    {
        $matches = $this->gameMatchRepository->findMatchesForGroupStanding();
        $byGroup = [];

        foreach ($matches as $match) {
            $letter = GameMatch::extractGroupStandingLetter($match->getPhase());
            if (null === $letter) {
                continue;
            }
            $byGroup[$letter][] = $match;
        }

        $result = [];
        foreach ($byGroup as $letter => $groupMatches) {
            $result[$letter] = $this->computeStandingsForGroup($groupMatches);
        }

        $this->mergeCountriesWithoutMatchRows($result);

        ksort($result, SORT_NATURAL);

        return $result;
    }

    /**
     * Ajoute les pays assignés en admin (groupe) ou reconnus via la grille, absents des matchs « Group X ».
     *
     * @param array<string, list<array<string, mixed>>> $result
     */
    private function mergeCountriesWithoutMatchRows(array &$result): void
    {
        $seenByGroup = [];
        foreach ($result as $letter => $rows) {
            $seenByGroup[$letter] = [];
            foreach ($rows as $row) {
                $id = $row['country']->getId();
                if (null !== $id) {
                    $seenByGroup[$letter][$id] = true;
                }
            }
        }

        foreach ($this->countryRepository->findAllOrderedByName() as $country) {
            $letter = $country->getGroupe() ?? WorldCup2026Groups::resolveGroupLetterForTeam((string) $country->getNom());
            if (null === $letter) {
                continue;
            }

            $countryId = $country->getId();
            if (null === $countryId) {
                continue;
            }

            if (!isset($result[$letter])) {
                $result[$letter] = [];
                $seenByGroup[$letter] = [];
            }

            if (isset($seenByGroup[$letter][$countryId])) {
                continue;
            }

            $result[$letter][] = $this->emptyStandingRow($country);
            $seenByGroup[$letter][$countryId] = true;
        }

        foreach ($result as $letter => $rows) {
            $result[$letter] = $this->sortStandingRows($rows);
        }
    }

    /**
     * @return array{
     *     country: Country,
     *     joues: int,
     *     victoires: int,
     *     nuls: int,
     *     defaites: int,
     *     bp: int,
     *     bc: int,
     *     diff: int,
     *     points: int
     * }
     */
    private function emptyStandingRow(Country $country): array
    {
        return [
            'country' => $country,
            'joues' => 0,
            'victoires' => 0,
            'nuls' => 0,
            'defaites' => 0,
            'bp' => 0,
            'bc' => 0,
            'diff' => 0,
            'points' => 0,
        ];
    }

    /**
     * @param list<GameMatch> $groupMatches
     *
     * @return list<array{
     *     country: Country,
     *     joues: int,
     *     victoires: int,
     *     nuls: int,
     *     defaites: int,
     *     bp: int,
     *     bc: int,
     *     diff: int,
     *     points: int
     * }>
     */
    private function computeStandingsForGroup(array $groupMatches): array
    {
        /** @var array<int, array{country: Country, joues: int, victoires: int, nuls: int, defaites: int, bp: int, bc: int}> $acc */
        $acc = [];

        foreach ($groupMatches as $match) {
            $home = $match->getPaysDomicile();
            $away = $match->getPaysExterieur();
            if (!$home instanceof Country || !$away instanceof Country) {
                continue;
            }
            foreach ([$home, $away] as $c) {
                $id = (int) $c->getId();
                if (!isset($acc[$id])) {
                    $acc[$id] = [
                        'country' => $c,
                        'joues' => 0,
                        'victoires' => 0,
                        'nuls' => 0,
                        'defaites' => 0,
                        'bp' => 0,
                        'bc' => 0,
                    ];
                }
            }
        }

        foreach ($groupMatches as $match) {
            if (!$this->isPlayed($match)) {
                continue;
            }
            $home = $match->getPaysDomicile();
            $away = $match->getPaysExterieur();
            if (!$home instanceof Country || !$away instanceof Country) {
                continue;
            }
            $sh = (int) $match->getScoreDomicile();
            $sa = (int) $match->getScoreExterieur();
            $hid = (int) $home->getId();
            $aid = (int) $away->getId();

            $acc[$hid]['joues']++;
            $acc[$aid]['joues']++;
            $acc[$hid]['bp'] += $sh;
            $acc[$hid]['bc'] += $sa;
            $acc[$aid]['bp'] += $sa;
            $acc[$aid]['bc'] += $sh;

            if ($sh > $sa) {
                $acc[$hid]['victoires']++;
                $acc[$aid]['defaites']++;
            } elseif ($sh < $sa) {
                $acc[$hid]['defaites']++;
                $acc[$aid]['victoires']++;
            } else {
                $acc[$hid]['nuls']++;
                $acc[$aid]['nuls']++;
            }
        }

        $rows = [];
        foreach ($acc as $row) {
            $diff = $row['bp'] - $row['bc'];
            $points = 3 * $row['victoires'] + $row['nuls'];
            $rows[] = [
                'country' => $row['country'],
                'joues' => $row['joues'],
                'victoires' => $row['victoires'],
                'nuls' => $row['nuls'],
                'defaites' => $row['defaites'],
                'bp' => $row['bp'],
                'bc' => $row['bc'],
                'diff' => $diff,
                'points' => $points,
            ];
        }

        return $this->sortStandingRows($rows);
    }

    /**
     * @param list<array{
     *     country: Country,
     *     joues: int,
     *     victoires: int,
     *     nuls: int,
     *     defaites: int,
     *     bp: int,
     *     bc: int,
     *     diff: int,
     *     points: int
     * }> $rows
     *
     * @return list<array{
     *     country: Country,
     *     joues: int,
     *     victoires: int,
     *     nuls: int,
     *     defaites: int,
     *     bp: int,
     *     bc: int,
     *     diff: int,
     *     points: int
     * }>
     */
    private function sortStandingRows(array $rows): array
    {
        usort($rows, static function (array $a, array $b): int {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }
            if ($a['diff'] !== $b['diff']) {
                return $b['diff'] <=> $a['diff'];
            }
            if ($a['bp'] !== $b['bp']) {
                return $b['bp'] <=> $a['bp'];
            }

            return strcmp((string) $a['country']->getNom(), (string) $b['country']->getNom());
        });

        return $rows;
    }

    private function isPlayed(GameMatch $match): bool
    {
        return null !== $match->getScoreDomicile() && null !== $match->getScoreExterieur();
    }
}
