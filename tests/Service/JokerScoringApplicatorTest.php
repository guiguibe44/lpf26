<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Repository\TeamJokerUsageRepository;
use App\Service\JokerScoringApplicator;
use App\Service\PronosticScoreInversionService;
use App\Service\PronosticSimulationService;
use PHPUnit\Framework\TestCase;

final class JokerScoringApplicatorTest extends TestCase
{
    private JokerScoringApplicator $applicator;

    protected function setUp(): void
    {
        $inversion = new PronosticScoreInversionService($this->createMock(TeamJokerUsageRepository::class));
        $this->applicator = new JokerScoringApplicator(new PronosticSimulationService($inversion));
    }

    public function testDoubleEquipeDoublesStandardPointsForGoodProno(): void
    {
        $match = $this->createMatch();
        $result = $this->applicator->applyForTeam(
            Joker::CODE_DOUBLE_EQUIPE,
            $match,
            2,
            1,
            2,
            1,
            9.0,
        );

        self::assertNotNull($result);
        self::assertSame(0.0, $result['playerPoints']);
        self::assertSame(18.0, $result['teamPoints']);
    }

    public function testDoubleEquipeAppliesFixedPenaltyForWrongProno(): void
    {
        $match = $this->createMatch();
        $result = $this->applicator->applyForTeam(
            Joker::CODE_DOUBLE_EQUIPE,
            $match,
            2,
            1,
            0,
            0,
            0.0,
        );

        self::assertNotNull($result);
        self::assertSame(0.0, $result['playerPoints']);
        self::assertSame(-5.0, $result['teamPoints']);
    }

    public function testReturnsNullWhenNoJoker(): void
    {
        $match = $this->createMatch();
        self::assertNull($this->applicator->applyForTeam(null, $match, 1, 0, 1, 0, 3.0));
    }

    private function createMatch(): GameMatch
    {
        $home = (new Country())->setNom('A');
        $away = (new Country())->setNom('B');

        return (new GameMatch())
            ->setPaysDomicile($home)
            ->setPaysExterieur($away)
            ->setDateHeure(new \DateTimeImmutable());
    }
}
