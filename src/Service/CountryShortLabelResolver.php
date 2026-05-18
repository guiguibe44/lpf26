<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Country;

/**
 * Libellé court affiché sur les cartes match (ex. FRA, ENG).
 */
final class CountryShortLabelResolver
{
    /** @var array<string, string> clé normalisée (nom) => code 3 lettres */
    private const CODES_BY_NAME = [
        'algeria' => 'ALG',
        'allemagne' => 'GER',
        'argentina' => 'ARG',
        'australia' => 'AUS',
        'austria' => 'AUT',
        'belgium' => 'BEL',
        'bosnia and herzegovina' => 'BIH',
        'bosnie herzegovine' => 'BIH',
        'brazil' => 'BRA',
        'bresil' => 'BRA',
        'brésil' => 'BRA',
        'canada' => 'CAN',
        'cape verde' => 'CPV',
        'colombia' => 'COL',
        'congo dr' => 'COD',
        'croatia' => 'CRO',
        'croatie' => 'CRO',
        'curacao' => 'CUW',
        'czechia' => 'CZE',
        'dr congo' => 'COD',
        'ecuador' => 'ECU',
        'egypt' => 'EGY',
        'england' => 'ENG',
        'angleterre' => 'ENG',
        'espagne' => 'ESP',
        'france' => 'FRA',
        'germany' => 'GER',
        'ghana' => 'GHA',
        'haiti' => 'HAI',
        'iran' => 'IRN',
        'iraq' => 'IRQ',
        'ivory coast' => 'CIV',
        'japan' => 'JPN',
        'jordan' => 'JOR',
        'mexico' => 'MEX',
        'mexique' => 'MEX',
        'morocco' => 'MAR',
        'maroc' => 'MAR',
        'netherlands' => 'NED',
        'new zealand' => 'NZL',
        'norway' => 'NOR',
        'norvege' => 'NOR',
        'norvège' => 'NOR',
        'panama' => 'PAN',
        'paraguay' => 'PAR',
        'poland' => 'POL',
        'pologne' => 'POL',
        'portugal' => 'POR',
        'qatar' => 'QAT',
        'saudi arabia' => 'KSA',
        'scotland' => 'SCO',
        'ecosse' => 'SCO',
        'senegal' => 'SEN',
        'south africa' => 'RSA',
        'south korea' => 'KOR',
        'spain' => 'ESP',
        'sweden' => 'SWE',
        'suisse' => 'SUI',
        'switzerland' => 'SUI',
        'tunisia' => 'TUN',
        'turkiye' => 'TUR',
        'turquie' => 'TUR',
        'united states' => 'USA',
        'uruguay' => 'URU',
        'uzbekistan' => 'UZB',
    ];

    public function resolve(?Country $country): string
    {
        if (!$country instanceof Country) {
            return '-';
        }

        $nom = trim((string) $country->getNom());
        if ('' === $nom) {
            return '-';
        }

        $key = $this->normalizeNameKey($nom);
        if (isset(self::CODES_BY_NAME[$key])) {
            return self::CODES_BY_NAME[$key];
        }

        $fromFlag = $this->codeFromDrapeauFilename($country->getDrapeau());
        if (null !== $fromFlag) {
            return $fromFlag;
        }

        return $this->fallbackFromName($nom);
    }

    private function normalizeNameKey(string $name): string
    {
        $normalized = mb_strtolower(trim($name));
        $normalized = str_replace(['’', "'"], ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized;
    }

    private function codeFromDrapeauFilename(?string $drapeau): ?string
    {
        if (null === $drapeau || '' === $drapeau) {
            return null;
        }

        $basename = basename($drapeau);
        if (!preg_match('/^(.+)-[a-f0-9]{12}\.[a-z0-9]+$/i', $basename, $matches)) {
            return null;
        }

        $slug = str_replace('-', ' ', $matches[1]);

        return $this->fallbackFromName($slug);
    }

    private function fallbackFromName(string $name): string
    {
        $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII', $name);
        $ascii = is_string($ascii) ? $ascii : $name;
        $ascii = preg_replace('/[^a-zA-Z0-9\s]/', '', $ascii) ?? $ascii;
        $words = preg_split('/\s+/', trim($ascii), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) {
            return '-';
        }

        if (count($words) >= 2) {
            $code = '';
            foreach (array_slice($words, 0, 3) as $word) {
                $code .= mb_strtoupper(mb_substr($word, 0, 1));
            }

            return mb_substr($code, 0, 3);
        }

        return mb_strtoupper(mb_substr($words[0], 0, 3));
    }
}
