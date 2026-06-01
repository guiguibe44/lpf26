<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Détermine la qualification pour les 16es de finale (top 2 + 8 meilleurs 3e) sur les classements de poule.
 */
final class GroupKnockoutQualificationAnalyzer
{
    private const MATCHES_PER_TEAM = 3;

    private const BEST_THIRD_PLACES_COUNT = 8;

    public const STATUS_QUALIFIED_DIRECT = 'qualified_direct';

    public const STATUS_QUALIFIED_THIRD = 'qualified_third';

    public const STATUS_ELIMINATED = 'eliminated';

    public const STATUS_LIVE = 'live';

    /**
     * @param array<string, list<array{
     *     country: \App\Entity\Country,
     *     joues: int,
     *     victoires: int,
     *     nuls: int,
     *     defaites: int,
     *     bp: int,
     *     bc: int,
     *     diff: int,
     *     points: int
     * }>> $standingsByGroup
     *
     * @return array<string, list<array{
     *     country: \App\Entity\Country,
     *     joues: int,
     *     victoires: int,
     *     nuls: int,
     *     defaites: int,
     *     bp: int,
     *     bc: int,
     *     diff: int,
     *     points: int,
     *     knockout_max_points: int,
     *     knockout_status: string
     * }>>
     */
    public function enrich(array $standingsByGroup): array
    {
        $enriched = [];

        foreach ($standingsByGroup as $letter => $rows) {
            $enriched[$letter] = array_map(static function (array $row): array {
                $row['knockout_max_points'] = (int) $row['points'] + 3 * (self::MATCHES_PER_TEAM - (int) $row['joues']);
                $row['knockout_status'] = self::STATUS_LIVE;

                return $row;
            }, $rows);
        }

        $thirdPlaceCandidates = [];

        foreach ($enriched as $letter => $rows) {
            $groupStarted = $this->hasEveryTeamPlayedAtLeastOnce($rows);

            foreach ($rows as $index => $row) {
                $rank = $index + 1;

                if ((int) $row['joues'] < self::MATCHES_PER_TEAM) {
                    continue;
                }

                if ($rank <= 2) {
                    $enriched[$letter][$index]['knockout_status'] = self::STATUS_QUALIFIED_DIRECT;

                    continue;
                }

                if (4 === $rank && $groupStarted) {
                    $enriched[$letter][$index]['knockout_status'] = self::STATUS_ELIMINATED;

                    continue;
                }

                if (3 === $rank && $groupStarted) {
                    $thirdPlaceCandidates[] = [
                        'letter' => $letter,
                        'index' => $index,
                        'row' => $row,
                    ];
                }
            }
        }

        usort($thirdPlaceCandidates, fn (array $a, array $b): int => $this->compareStandingRows($a['row'], $b['row']));

        foreach ($thirdPlaceCandidates as $candidateIndex => $candidate) {
            $status = $candidateIndex < self::BEST_THIRD_PLACES_COUNT
                ? self::STATUS_QUALIFIED_THIRD
                : self::STATUS_ELIMINATED;

            $letter = $candidate['letter'];
            $index = $candidate['index'];
            $enriched[$letter][$index]['knockout_status'] = $status;
        }

        foreach ($enriched as $letter => $rows) {
            if (!$this->hasEveryTeamPlayedAtLeastOnce($rows)) {
                continue;
            }

            $liveOrder = $this->sortByLiveProjection($rows);

            foreach ($rows as $index => $row) {
                if (self::STATUS_LIVE !== $row['knockout_status']) {
                    continue;
                }

                $liveRank = $this->findLiveRank($row, $liveOrder);
                if ($liveRank >= 3) {
                    $enriched[$letter][$index]['knockout_status'] = self::STATUS_ELIMINATED;
                }
            }
        }

        return $enriched;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function hasEveryTeamPlayedAtLeastOnce(array $rows): bool
    {
        if ([] === $rows) {
            return false;
        }

        foreach ($rows as $row) {
            if ((int) $row['joues'] < 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function sortByLiveProjection(array $rows): array
    {
        $sorted = $rows;
        usort($sorted, fn (array $a, array $b): int => $this->compareStandingRows($a, $b, true));

        return $sorted;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $liveOrder
     */
    private function findLiveRank(array $row, array $liveOrder): int
    {
        $country = $row['country'];

        foreach ($liveOrder as $index => $liveRow) {
            $other = $liveRow['country'];
            if ($other === $country) {
                return $index;
            }

            $countryId = $country->getId();
            $otherId = $other->getId();
            if (null !== $countryId && null !== $otherId && $countryId === $otherId) {
                return $index;
            }
        }

        return 3;
    }

    /**
     * @param array<string, mixed> $a
     * @param array<string, mixed> $b
     */
    private function compareStandingRows(array $a, array $b, bool $useMaxPoints = false): int
    {
        $aKey = $useMaxPoints ? (int) $a['knockout_max_points'] : (int) $a['points'];
        $bKey = $useMaxPoints ? (int) $b['knockout_max_points'] : (int) $b['points'];

        if ($aKey !== $bKey) {
            return $bKey <=> $aKey;
        }

        if ($a['diff'] !== $b['diff']) {
            return (int) $b['diff'] <=> (int) $a['diff'];
        }

        if ($a['bp'] !== $b['bp']) {
            return (int) $b['bp'] <=> (int) $a['bp'];
        }

        return strcmp((string) $a['country']->getNom(), (string) $b['country']->getNom());
    }
}
