<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Grille indicative CDM 2026 (noms équipes alignés sur l’import FIFA / API-Sports).
 * Sert à retrouver « Group X » quand l’API ne renvoie qu’un libellé générique (ex. « Group Stage »).
 */
final class WorldCup2026Groups
{
    /**
     * @var array<string, list<string>>
     */
    public const TEAMS_BY_LETTER = [
        'A' => ['Mexico', 'South Africa', 'South Korea', 'Czechia'],
        'B' => ['Canada', 'Bosnia and Herzegovina', 'Qatar', 'Switzerland'],
        'C' => ['Brazil', 'Morocco', 'Haiti', 'Scotland'],
        'D' => ['United States', 'Paraguay', 'Australia', 'Turkiye'],
        'E' => ['Germany', 'Curacao', 'Ivory Coast', 'Ecuador'],
        'F' => ['Netherlands', 'Japan', 'Sweden', 'Tunisia'],
        'G' => ['Belgium', 'Egypt', 'Iran', 'New Zealand'],
        'H' => ['Spain', 'Cape Verde', 'Saudi Arabia', 'Uruguay'],
        'I' => ['France', 'Senegal', 'Iraq', 'Norway'],
        'J' => ['Argentina', 'Algeria', 'Austria', 'Jordan'],
        'K' => ['Portugal', 'DR Congo', 'Uzbekistan', 'Colombia'],
        'L' => ['England', 'Croatia', 'Ghana', 'Panama'],
    ];

    public static function normalizeTeamKey(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * Si les deux équipes appartiennent au même groupe de cette grille, retourne « Group A », … « Group L ».
     */
    public static function resolveGroupPhase(string $homeTeamName, string $awayTeamName): ?string
    {
        $hk = self::normalizeTeamKey($homeTeamName);
        $ak = self::normalizeTeamKey($awayTeamName);

        foreach (self::TEAMS_BY_LETTER as $letter => $teams) {
            $keys = [];
            foreach ($teams as $t) {
                $keys[self::normalizeTeamKey($t)] = true;
            }
            if (isset($keys[$hk], $keys[$ak])) {
                return 'Group '.$letter;
            }
        }

        return null;
    }
}
