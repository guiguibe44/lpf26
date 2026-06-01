<?php

declare(strict_types=1);

namespace App\TeamRecap;

use App\Entity\Joker;

/**
 * Identifiants de situation pour les GIFs du récap d’équipe (un tirage aléatoire parmi les fichiers du slot).
 */
final class TeamRecapGifSlot
{
    public const SUBJECT_HOT = 'subject.hot';

    public const SUBJECT_POSITIVE = 'subject.positive';

    public const SUBJECT_NEUTRAL = 'subject.neutral';

    public static function subjectCodeForTeamPoints(int $totalTeamPoints): string
    {
        if ($totalTeamPoints >= 50) {
            return self::SUBJECT_HOT;
        }

        if ($totalTeamPoints > 0) {
            return self::SUBJECT_POSITIVE;
        }

        return self::SUBJECT_NEUTRAL;
    }

    public static function jokerUseful(string $jokerCode): string
    {
        return 'joker.'.$jokerCode.'.useful';
    }

    public static function jokerNotUseful(string $jokerCode): string
    {
        return 'joker.'.$jokerCode.'.not_useful';
    }

    /**
     * @param list<Joker> $jokers
     *
     * @return array<string, string> libellé admin => valeur slot
     */
    public static function adminChoiceList(array $jokers): array
    {
        $choices = [
            'Objet e-mail — grosse période (≥ 50 pts équipe)' => self::SUBJECT_HOT,
            'Objet e-mail — période positive (> 0 pt)' => self::SUBJECT_POSITIVE,
            'Objet e-mail — période calme (0 pt)' => self::SUBJECT_NEUTRAL,
        ];

        foreach ($jokers as $joker) {
            $code = (string) $joker->getCode();
            if ('' === $code) {
                continue;
            }

            $title = $joker->getDisplayTitle();
            $choices[sprintf('Joker « %s » — utile', $title)] = self::jokerUseful($code);
            $choices[sprintf('Joker « %s » — pas utile', $title)] = self::jokerNotUseful($code);
        }

        return $choices;
    }

    public static function adminLabelFor(string $slot): string
    {
        return match ($slot) {
            self::SUBJECT_HOT => 'Objet — grosse période',
            self::SUBJECT_POSITIVE => 'Objet — période positive',
            self::SUBJECT_NEUTRAL => 'Objet — période calme',
            default => self::adminLabelForJokerSlot($slot),
        };
    }

    private static function adminLabelForJokerSlot(string $slot): string
    {
        if (preg_match('/^joker\.(.+)\.(useful|not_useful)$/', $slot, $m)) {
            $suffix = 'useful' === $m[2] ? 'utile' : 'pas utile';

            return sprintf('Joker %s — %s', str_replace('_', ' ', $m[1]), $suffix);
        }

        return $slot;
    }
}
