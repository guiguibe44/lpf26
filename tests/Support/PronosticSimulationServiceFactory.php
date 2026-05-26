<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Repository\TeamJokerUsageRepository;
use App\Service\MatchCoteExactScoreCalculator;
use App\Service\MatchCoteOneNTwoCalculator;
use App\Service\MatchCoteService;
use App\Service\MatchOutcomeResolver;
use App\Service\PronosticScoreInversionService;
use App\Service\PronosticSimulationService;

final class PronosticSimulationServiceFactory
{
    public static function create(
        TeamJokerUsageRepository $usageRepository,
        string $matchCoteMode = 'one_n_two',
    ): PronosticSimulationService {
        $outcome = new MatchOutcomeResolver();
        $inversion = new PronosticScoreInversionService($usageRepository);

        return new PronosticSimulationService(
            $inversion,
            new MatchCoteService(
                $matchCoteMode,
                new MatchCoteOneNTwoCalculator($outcome),
                new MatchCoteExactScoreCalculator(),
                $outcome,
            ),
        );
    }
}
