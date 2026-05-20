<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\SimulatedPronosticLine;
use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Repository\JokerRepository;
use App\Repository\TeamJokerUsageRepository;
use App\Service\JokerScoringApplicator;
use App\Service\PronosticCalcDisplayService;
use App\Service\PronosticSimulationService;
use PHPUnit\Framework\TestCase;

final class PronosticCalcDisplayServiceTest extends TestCase
{
    public function testDoubleEquipeShowsMultiplierFactor(): void
    {
        $match = new GameMatch();
        $line = new SimulatedPronosticLine(1, 5, 'Joueur 1', 2, 1, 3, 1.5, 9.0, false, 9.0);

        $joker = (new Joker())
            ->setCode(Joker::CODE_DOUBLE_EQUIPE)
            ->setTitle('La Mexicaine')
            ->setName('La Mexicaine');

        $jokerRepo = $this->createMock(JokerRepository::class);
        $jokerRepo->method('findAllOrdered')->willReturn([$joker]);

        $usageRepo = $this->createMock(TeamJokerUsageRepository::class);
        $usageRepo->method('findJokerCodesByTeamForMatch')->willReturn([5 => Joker::CODE_DOUBLE_EQUIPE]);
        $usageRepo->method('findPiquePointsTargetsByTeamForMatch')->willReturn([]);
        $usageRepo->method('findCollecteTeamIdsForMatch')->willReturn([]);

        $scoring = new JokerScoringApplicator(new PronosticSimulationService(
            new \App\Service\PronosticScoreInversionService($usageRepo),
        ));

        $service = new PronosticCalcDisplayService($scoring, $usageRepo, $jokerRepo);

        $enriched = $service->enrich($match, 2, 1, [$line], [$line], [$line]);
        self::assertCount(1, $enriched[0]->calcMultipliers);
        self::assertSame('2', $enriched[0]->calcMultipliers[0]['factor']);
        self::assertSame('La Mexicaine', $enriched[0]->calcMultipliers[0]['label']);
    }
}
