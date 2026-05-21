<?php

declare(strict_types=1);

namespace App\GameTerminology;

/**
 * Libellés « BigBalls » : deux coéquipiers pronostiquent le même score sur un match.
 */
final class BigBallsTerms
{
    public const NAME = 'BigBalls';

    public const NAME_DEFINITE = 'BigBalls';

    public const NAME_PLURAL = 'BigBalls';

    public const BADGE = 'BigBalls';

    public const COLUMN_RATIO = 'BigBalls';

    public const COLUMN_RATIO_TITLE = 'BigBalls réussis / tentés (même score des 2 coéquipiers ; réussi = exact ou bon 1/N/2)';

    public const TEAM_SHOW_ATTEMPTED = 'BigBalls tentés';

    public const TEAM_SHOW_SUCCEEDED = 'BigBalls réussis';

    public static function get(string $key): string
    {
        return match ($key) {
            'name' => self::NAME,
            'name_definite' => self::NAME_DEFINITE,
            'name_plural' => self::NAME_PLURAL,
            'badge' => self::BADGE,
            'column_ratio' => self::COLUMN_RATIO,
            'column_ratio_title' => self::COLUMN_RATIO_TITLE,
            'team_show_attempted' => self::TEAM_SHOW_ATTEMPTED,
            'team_show_succeeded' => self::TEAM_SHOW_SUCCEEDED,
            default => throw new \InvalidArgumentException(sprintf('Clé BigBallsTerms inconnue : %s', $key)),
        };
    }
}
