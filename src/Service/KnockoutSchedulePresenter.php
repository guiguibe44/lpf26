<?php

declare(strict_types=1);

namespace App\Service;

use App\Data\WorldCup2026KnockoutFixtures;

final class KnockoutSchedulePresenter
{
    public function __construct(
        private readonly KnockoutFixtureLabelResolver $labelResolver,
    ) {
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     matches: list<array{
     *         id: string,
     *         match_number: ?int,
     *         home_label: string,
     *         away_label: string,
     *         date: string,
     *         time: string,
     *         venue: string,
     *         city: string,
     *         kickoff_label: string
     *     }>
     * }>
     */
    public function buildRounds(): array
    {
        $byRound = [];
        foreach (WorldCup2026KnockoutFixtures::all() as $fixture) {
            $roundKey = $fixture['round'];
            $byRound[$roundKey][] = $this->presentMatch($fixture);
        }

        $rounds = [];
        foreach (WorldCup2026KnockoutFixtures::roundOrder() as $roundKey) {
            if (!isset($byRound[$roundKey])) {
                continue;
            }

            $rounds[] = [
                'key' => $roundKey,
                'label' => WorldCup2026KnockoutFixtures::roundLabels()[$roundKey],
                'matches' => $byRound[$roundKey],
            ];
        }

        return $rounds;
    }

    /**
     * Tableau de compétition : colonnes alignées (16es → 1/2) + finale / 3e place.
     *
     * @return array{
     *     grid_rows: int,
     *     columns: list<array{key: string, label: string, short_label: string, matches: list<array<string, mixed>>}>,
     *     final_match: ?array<string, mixed>,
     *     third_place_match: ?array<string, mixed>
     * }
     */
    public function buildBracket(): array
    {
        $byKey = [];
        foreach ($this->buildRounds() as $round) {
            $byKey[$round['key']] = $round;
        }

        $gridRows = 16;
        $mainRoundKeys = [
            WorldCup2026KnockoutFixtures::ROUND_OF_32,
            WorldCup2026KnockoutFixtures::ROUND_OF_16,
            WorldCup2026KnockoutFixtures::QUARTER_FINAL,
            WorldCup2026KnockoutFixtures::SEMI_FINAL,
        ];

        $shortLabels = WorldCup2026KnockoutFixtures::roundShortLabels();

        /** @var array<int, array{row_start: int, row_span: int}> $matchGrid */
        $matchGrid = [];
        $rawByRound = [];
        foreach (WorldCup2026KnockoutFixtures::all() as $fixture) {
            $rawByRound[$fixture['round']][] = $fixture;
        }

        $columns = [];
        foreach ($mainRoundKeys as $roundKey) {
            if (!isset($byKey[$roundKey])) {
                continue;
            }

            $round = $byKey[$roundKey];
            $rawMatches = $rawByRound[$roundKey] ?? [];
            $matches = [];

            foreach ($round['matches'] as $index => $match) {
                $raw = $rawMatches[$index] ?? null;
                $matchNumber = $match['match_number'];
                $gridPosition = $this->resolveBracketGridPosition(
                    $roundKey,
                    \is_array($raw) ? (string) $raw['home_code'] : '',
                    \is_array($raw) ? (string) $raw['away_code'] : '',
                    $index,
                    $matchGrid,
                );

                if (null !== $matchNumber && null !== $gridPosition) {
                    $matchGrid[$matchNumber] = $gridPosition;
                }

                $matches[] = array_merge($match, $gridPosition ?? ['row_start' => 1, 'row_span' => 1]);
            }

            $columns[] = [
                'key' => $roundKey,
                'label' => $round['label'],
                'short_label' => $shortLabels[$roundKey],
                'matches' => $matches,
            ];
        }

        $finalRound = $byKey[WorldCup2026KnockoutFixtures::FINAL] ?? null;
        $thirdRound = $byKey[WorldCup2026KnockoutFixtures::THIRD_PLACE] ?? null;

        return [
            'grid_rows' => $gridRows,
            'columns' => $columns,
            'final_match' => $finalRound['matches'][0] ?? null,
            'third_place_match' => $thirdRound['matches'][0] ?? null,
        ];
    }

    /**
     * Aligne chaque match sur les créneaux de ses affiches (ex. W73 vs W75, pas 73 vs 74).
     *
     * @param array<int, array{row_start: int, row_span: int}> $matchGrid
     *
     * @return array{row_start: int, row_span: int}|null
     */
    private function resolveBracketGridPosition(
        string $roundKey,
        string $homeCode,
        string $awayCode,
        int $indexInRound,
        array $matchGrid,
    ): ?array {
        if (WorldCup2026KnockoutFixtures::ROUND_OF_32 === $roundKey) {
            return [
                'row_start' => $indexInRound + 1,
                'row_span' => 1,
            ];
        }

        $homeFeeder = $this->extractFeederMatchNumber($homeCode);
        $awayFeeder = $this->extractFeederMatchNumber($awayCode);
        if (null === $homeFeeder || null === $awayFeeder) {
            return null;
        }

        $homeGrid = $matchGrid[$homeFeeder] ?? null;
        $awayGrid = $matchGrid[$awayFeeder] ?? null;
        if (null === $homeGrid || null === $awayGrid) {
            return null;
        }

        $homeEnd = $homeGrid['row_start'] + $homeGrid['row_span'] - 1;
        $awayEnd = $awayGrid['row_start'] + $awayGrid['row_span'] - 1;
        $rowStart = min($homeGrid['row_start'], $awayGrid['row_start']);
        $rowEnd = max($homeEnd, $awayEnd);

        return [
            'row_start' => $rowStart,
            'row_span' => $rowEnd - $rowStart + 1,
        ];
    }

    private function extractFeederMatchNumber(string $code): ?int
    {
        if (1 === preg_match('/^W(\d+)$/i', trim($code), $matches)) {
            return (int) $matches[1];
        }

        if (1 === preg_match('/^RU(\d+)$/i', trim($code), $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $fixture
     *
     * @return array<string, mixed>
     */
    private function presentMatch(array $fixture): array
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $fixture['date']) ?: null;
        $kickoffLabel = null !== $date
            ? sprintf('%s · %s', $date->format('d/m/Y'), (string) $fixture['time'])
            : (string) $fixture['time'];

        $kickoffShort = null !== $date
            ? sprintf('%s · %s', $date->format('d/m'), (string) $fixture['time'])
            : (string) $fixture['time'];

        return [
            'id' => (string) $fixture['id'],
            'match_number' => $fixture['match_number'],
            'home_label' => $this->labelResolver->resolve((string) $fixture['home_code']),
            'away_label' => $this->labelResolver->resolve((string) $fixture['away_code']),
            'date' => (string) $fixture['date'],
            'time' => (string) $fixture['time'],
            'venue' => (string) $fixture['venue'],
            'city' => (string) $fixture['city'],
            'kickoff_label' => $kickoffLabel,
            'kickoff_short' => $kickoffShort,
        ];
    }
}
