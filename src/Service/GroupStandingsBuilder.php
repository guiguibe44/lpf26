<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Repository\GameMatchRepository;

/**
 * Construit les classements de phase de groupes à partir des matchs (phase « Group X ») et des scores.
 */
final class GroupStandingsBuilder
{
    public function __construct(
        private readonly GameMatchRepository $gameMatchRepository,
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

        ksort($result, SORT_NATURAL);

        return $result;
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
