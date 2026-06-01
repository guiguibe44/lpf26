<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Calendrier indicatif des phases finales CDM 2026 (hors base LPF).
 *
 * Source : calendrier FIFA (juin–juillet 2026), heures affichées comme sur fifa.com (locale stade).
 *
 * @see https://www.fifa.com/fr/tournaments/mens/worldcup/canadamexicousa2026/scores-fixtures
 * @see https://www.fifa.com/fr/tournaments/mens/worldcup/canadamexicousa2026/articles/calendrier-phase-a-elimination-directe
 */
final class WorldCup2026KnockoutFixtures
{
    /** Premier tour à élimination directe (32 équipes, 16 matchs) — « 16èmes » en français. */
    public const ROUND_OF_32 = 'round_of_32';
    /** Second tour (16 équipes, 8 matchs) — « 8èmes » en français. */
    public const ROUND_OF_16 = 'round_of_16';
    public const QUARTER_FINAL = 'quarter_final';
    public const SEMI_FINAL = 'semi_final';
    public const THIRD_PLACE = 'third_place';
    public const FINAL = 'final';

    /**
     * @return list<array{
     *     id: string,
     *     round: string,
     *     round_label: string,
     *     match_number: ?int,
     *     home_code: string,
     *     away_code: string,
     *     date: string,
     *     time: string,
     *     venue: string,
     *     city: string
     * }>
     */
    public static function all(): array
    {
        return array_merge(
            self::roundOf32(),
            self::roundOf16(),
            self::quarterFinals(),
            self::semiFinals(),
            self::thirdPlace(),
            self::final(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function roundLabels(): array
    {
        return [
            self::ROUND_OF_32 => '16èmes de finale',
            self::ROUND_OF_16 => '8èmes de finale',
            self::QUARTER_FINAL => 'Quarts de finale',
            self::SEMI_FINAL => 'Demi-finales',
            self::THIRD_PLACE => 'Match pour la 3e place',
            self::FINAL => 'Finale',
        ];
    }

    /**
     * Libellés courts pour le tableau (accessibilité, tooltips).
     *
     * @return array<string, string>
     */
    public static function roundShortLabels(): array
    {
        return [
            self::ROUND_OF_32 => '16es',
            self::ROUND_OF_16 => '8es',
            self::QUARTER_FINAL => '1/4',
            self::SEMI_FINAL => '1/2',
            self::THIRD_PLACE => '3e place',
            self::FINAL => 'Finale',
        ];
    }

    /**
     * @return list<string>
     */
    public static function roundOrder(): array
    {
        return [
            self::ROUND_OF_32,
            self::ROUND_OF_16,
            self::QUARTER_FINAL,
            self::SEMI_FINAL,
            self::THIRD_PLACE,
            self::FINAL,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function roundOf32(): array
    {
        $round = self::ROUND_OF_32;
        $label = self::roundLabels()[$round];

        return [
            self::row($round, $label, 73, '2A', '2B', '2026-06-28', '19:00', 'Stade de Los Angeles', 'Los Angeles'),
            self::row($round, $label, 74, '1C', '2F', '2026-06-29', '17:00', 'Stade de Houston', 'Houston'),
            self::row($round, $label, 75, '1E', '3ABCDF', '2026-06-29', '20:30', 'Stade de Boston', 'Boston'),
            self::row($round, $label, 76, '1F', '2C', '2026-06-30', '01:00', 'Stade de Monterrey', 'Monterrey'),
            self::row($round, $label, 77, '2E', '2I', '2026-06-30', '17:00', 'Stade de Dallas', 'Dallas'),
            self::row($round, $label, 78, '1I', '3CDFGH', '2026-06-30', '21:00', 'Stade de New York/New Jersey', 'New York'),
            self::row($round, $label, 79, '1A', '3CEFHI', '2026-07-01', '01:00', 'Stade de Mexico', 'Mexico'),
            self::row($round, $label, 80, '1L', '3EHIJK', '2026-07-01', '16:00', 'Stade d\'Atlanta', 'Atlanta'),
            self::row($round, $label, 81, '1G', '3AEHIJ', '2026-07-01', '20:00', 'Stade de Seattle', 'Seattle'),
            self::row($round, $label, 82, '1D', '3BEFIJ', '2026-07-02', '00:00', 'Stade de la baie de San Francisco', 'San Francisco'),
            self::row($round, $label, 83, '1H', '2J', '2026-07-02', '19:00', 'Stade de Los Angeles', 'Los Angeles'),
            self::row($round, $label, 84, '2K', '2L', '2026-07-02', '23:00', 'Stade de Toronto', 'Toronto'),
            self::row($round, $label, 85, '1B', '3EFGIJ', '2026-07-03', '03:00', 'BC Place', 'Vancouver'),
            self::row($round, $label, 86, '2D', '2G', '2026-07-03', '18:00', 'Stade de Dallas', 'Dallas'),
            self::row($round, $label, 87, '1J', '2H', '2026-07-03', '22:00', 'Stade de Miami', 'Miami'),
            self::row($round, $label, 88, '1K', '3DEIJL', '2026-07-04', '01:30', 'Stade de Kansas City', 'Kansas City'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function roundOf16(): array
    {
        $round = self::ROUND_OF_16;
        $label = self::roundLabels()[$round];

        return [
            self::row($round, $label, 89, 'W73', 'W75', '2026-07-04', '17:00', 'Stade de Houston', 'Houston'),
            self::row($round, $label, 90, 'W74', 'W77', '2026-07-04', '21:00', 'Stade de Philadelphie', 'Philadelphie'),
            self::row($round, $label, 91, 'W76', 'W78', '2026-07-05', '20:00', 'Stade de New York/New Jersey', 'New York'),
            self::row($round, $label, 92, 'W79', 'W80', '2026-07-06', '00:00', 'Stade de Mexico', 'Mexico'),
            self::row($round, $label, 93, 'W83', 'W84', '2026-07-06', '19:00', 'Stade de Dallas', 'Dallas'),
            self::row($round, $label, 94, 'W81', 'W82', '2026-07-07', '00:00', 'Stade de Seattle', 'Seattle'),
            self::row($round, $label, 95, 'W86', 'W88', '2026-07-07', '16:00', 'Stade d\'Atlanta', 'Atlanta'),
            self::row($round, $label, 96, 'W85', 'W87', '2026-07-07', '20:00', 'BC Place', 'Vancouver'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function quarterFinals(): array
    {
        $round = self::QUARTER_FINAL;
        $label = self::roundLabels()[$round];

        return [
            self::row($round, $label, 97, 'W89', 'W90', '2026-07-09', '20:00', 'Stade de Boston', 'Boston'),
            self::row($round, $label, 98, 'W93', 'W94', '2026-07-10', '19:00', 'Stade de Los Angeles', 'Los Angeles'),
            self::row($round, $label, 99, 'W91', 'W92', '2026-07-11', '21:00', 'Stade de Miami', 'Miami'),
            self::row($round, $label, 100, 'W95', 'W96', '2026-07-12', '01:00', 'Stade de Kansas City', 'Kansas City'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function semiFinals(): array
    {
        $round = self::SEMI_FINAL;
        $label = self::roundLabels()[$round];

        return [
            self::row($round, $label, 101, 'W97', 'W98', '2026-07-14', '15:00', 'Stade de Dallas', 'Dallas'),
            self::row($round, $label, 102, 'W99', 'W100', '2026-07-15', '15:00', 'Stade d\'Atlanta', 'Atlanta'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function thirdPlace(): array
    {
        $round = self::THIRD_PLACE;
        $label = self::roundLabels()[$round];

        return [
            self::row($round, $label, 103, 'RU101', 'RU102', '2026-07-18', '21:00', 'Stade de Miami', 'Miami'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function final(): array
    {
        $round = self::FINAL;
        $label = self::roundLabels()[$round];

        return [
            self::row($round, $label, 104, 'W101', 'W102', '2026-07-19', '19:00', 'Stade de New York/New Jersey', 'New York'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(
        string $round,
        string $roundLabel,
        int $matchNumber,
        string $homeCode,
        string $awayCode,
        string $date,
        string $time,
        string $venue,
        string $city,
    ): array {
        return [
            'id' => sprintf('%s-%d', $round, $matchNumber),
            'round' => $round,
            'round_label' => $roundLabel,
            'match_number' => $matchNumber,
            'home_code' => $homeCode,
            'away_code' => $awayCode,
            'date' => $date,
            'time' => $time,
            'venue' => $venue,
            'city' => $city,
        ];
    }
}
