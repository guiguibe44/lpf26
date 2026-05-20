<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;

/**
 * Libellé sous le score live : minute API ou « En cours ».
 */
final class MatchLiveClockLabelResolver
{
    public function resolve(GameMatch $match): string
    {
        if ($this->usesApiLiveClock($match)) {
            $minute = $match->getLiveElapsedMinute();
            if (null !== $minute && $minute > 0) {
                return $minute."'";
            }
        }

        return 'En cours';
    }

    private function usesApiLiveClock(GameMatch $match): bool
    {
        return $match->isApiFootballSyncEnabled()
            && null !== $match->getApiFootballFixtureId();
    }
}
