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
     * Variantes de noms (API / admin / FR) → clé canonique de la grille.
     *
     * @var array<string, string>
     */
    private const NAME_ALIASES = [
        'czech republic' => 'czechia',
        'république tchèque' => 'czechia',
        'bosnia & herzegovina' => 'bosnia and herzegovina',
        'bosnie-herzégovine' => 'bosnia and herzegovina',
        'bosnie herzegovine' => 'bosnia and herzegovina',
        'turkey' => 'turkiye',
        'turquie' => 'turkiye',
        'türkiye' => 'turkiye',
        'curaçao' => 'curacao',
        'curacao' => 'curacao',
        'cap-vert' => 'cape verde',
        'cap vert' => 'cape verde',
        'cabo verde' => 'cape verde',
        'cape verde islands' => 'cape verde',
        'cap-vert islands' => 'cape verde',
        'iles du cap-vert' => 'cape verde',
        'îles du cap-vert' => 'cape verde',
        'usa' => 'united states',
        'united states of america' => 'united states',
        'korea republic' => 'south korea',
        'republic of korea' => 'south korea',
        'corée du sud' => 'south korea',
        'ivory coast' => 'ivory coast',
        'côte d\'ivoire' => 'ivory coast',
        'cote d\'ivoire' => 'ivory coast',
        'democratic republic of the congo' => 'dr congo',
        'congo dr' => 'dr congo',
    ];

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
        $key = mb_strtolower(trim($name));
        $key = self::stripAccents($key);
        $key = str_replace('&', 'and', $key);
        $key = preg_replace('/\s+/u', ' ', $key) ?? $key;

        return self::NAME_ALIASES[$key] ?? $key;
    }

    private static function stripAccents(string $value): string
    {
        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
            if (false !== $transliterator) {
                $ascii = $transliterator->transliterate($value);

                return \is_string($ascii) ? $ascii : $value;
            }
        }

        return strtr($value, [
            'ç' => 'c', 'ć' => 'c', 'ã' => 'a', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y', 'ł' => 'l', 'ń' => 'n', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        ]);
    }

    /**
     * Lettre de poule A–L pour un nom d’équipe, ou null si inconnu.
     */
    public static function resolveGroupLetterForTeam(string $teamName): ?string
    {
        $key = self::normalizeTeamKey($teamName);

        foreach (self::TEAMS_BY_LETTER as $letter => $teams) {
            foreach ($teams as $team) {
                if (self::normalizeTeamKey($team) === $key) {
                    return $letter;
                }
            }
        }

        return null;
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
