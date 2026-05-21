<?php

declare(strict_types=1);

namespace App\Joker;

use App\Entity\Joker;
use App\Enum\JokerLiveStoryCase;

/**
 * Cas de phrases éditables en admin selon le code technique du joker.
 */
final class JokerLiveStoryCasesForCode
{
    /**
     * @return list<JokerLiveStoryCase>
     */
    public static function forCode(?string $code): array
    {
        return match ($code) {
            Joker::CODE_BOUCLIER => [
                JokerLiveStoryCase::ShieldActive,
            ],
            Joker::CODE_ESPION => [
                JokerLiveStoryCase::Espion,
            ],
            Joker::CODE_DOUBLE_EQUIPE => [
                JokerLiveStoryCase::Placed,
                JokerLiveStoryCase::PointsGain,
                JokerLiveStoryCase::PointsLoss,
                JokerLiveStoryCase::PointsNeutral,
            ],
            Joker::CODE_DOUBLE_BUTEUR => [
                JokerLiveStoryCase::Placed,
                JokerLiveStoryCase::PointsGainButeur,
                JokerLiveStoryCase::PointsLossButeur,
                JokerLiveStoryCase::PointsNeutral,
            ],
            Joker::CODE_PIQUE_POINTS,
            Joker::CODE_INVERSE_SCORE => [
                JokerLiveStoryCase::PlacedOnTarget,
                JokerLiveStoryCase::Neutralized,
                JokerLiveStoryCase::PointsGain,
                JokerLiveStoryCase::PointsLoss,
                JokerLiveStoryCase::PointsNeutral,
            ],
            Joker::CODE_INVERSE_BUTEUR => [
                JokerLiveStoryCase::PlacedOnTarget,
                JokerLiveStoryCase::Neutralized,
                JokerLiveStoryCase::PointsGainButeur,
                JokerLiveStoryCase::PointsLossButeur,
                JokerLiveStoryCase::PointsNeutral,
            ],
            Joker::CODE_COLLECTE_POINTS => [
                JokerLiveStoryCase::Placed,
                JokerLiveStoryCase::PointsGain,
                JokerLiveStoryCase::PointsLoss,
            ],
            Joker::CODE_EQUIPE_FAVORITE => [
                JokerLiveStoryCase::Placed,
            ],
            default => [],
        };
    }

    public static function applies(?string $code, JokerLiveStoryCase $case): bool
    {
        return \in_array($case, self::forCode($code), true);
    }

    /**
     * Retire du JSON les clés qui ne correspondent pas au code du joker.
     */
    public static function pruneTemplatesForCode(?string $code, ?array $templates): ?array
    {
        if (null === $templates || [] === $templates) {
            return null;
        }

        $allowed = array_map(
            static fn (JokerLiveStoryCase $case): string => $case->value,
            self::forCode($code),
        );

        $pruned = array_intersect_key(
            $templates,
            array_fill_keys($allowed, true),
        );

        return [] === $pruned ? null : $pruned;
    }
}
