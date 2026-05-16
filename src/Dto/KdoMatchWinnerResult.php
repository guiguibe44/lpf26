<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Team;

final class KdoMatchWinnerResult
{
    public function __construct(
        public readonly Team $team,
        public readonly int $exactScoresCount,
    ) {
    }
}
