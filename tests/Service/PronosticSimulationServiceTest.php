<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\User;
use App\Repository\TeamJokerUsageRepository;
use App\Service\PronosticScoreInversionService;
use App\Service\PronosticSimulationService;
use PHPUnit\Framework\TestCase;

final class PronosticSimulationServiceTest extends TestCase
{
    private PronosticSimulationService $service;

    protected function setUp(): void
    {
        $inversion = new PronosticScoreInversionService($this->createMock(TeamJokerUsageRepository::class));
        $this->service = new PronosticSimulationService($inversion);
    }

    public function testExactScoreGetsHigherBaseAndCoteWhenRare(): void
    {
        $match = $this->createMatch();
        $unique = $this->pronostic($match, 1, 2, 1);
        $common = $this->pronostic($match, 2, 0, 0);
        $other = $this->pronostic($match, 3, 0, 0);

        $lines = $this->service->simulate($match, 2, 1, [$unique, $common, $other]);

        self::assertCount(3, $lines);
        $byId = [];
        foreach ($lines as $line) {
            $byId[$line->pronosticId] = $line;
        }

        self::assertSame(3, $byId[1]->basePoints);
        self::assertSame(3.0, $byId[1]->coefficient);
        self::assertSame(9.0, $byId[1]->points);
        self::assertSame(0, $byId[2]->basePoints);
        self::assertSame(1.5, $byId[2]->coefficient);
        self::assertSame(0.0, $byId[2]->points);
    }

    public function testTeamRiskWhenBothPlayersSameScore(): void
    {
        $match = $this->createMatch();
        $a = $this->pronostic($match, 1, 1, 0);
        $b = $this->pronostic($match, 2, 1, 0);
        $c = $this->pronostic($match, 3, 0, 0);

        $lines = $this->service->simulate($match, 1, 0, [$a, $b, $c], [1 => 10, 2 => 10]);

        $riskCount = 0;
        foreach ($lines as $line) {
            if ($line->priseRisque) {
                ++$riskCount;
            }
        }

        self::assertSame(2, $riskCount);
    }

    public function testInvertedTeamScoresUseSwappedPrediction(): void
    {
        $match = $this->createMatch();
        $a = $this->pronostic($match, 1, 1, 1);
        $b = $this->pronostic($match, 2, 3, 0);

        $lines = $this->service->simulate(
            $match,
            0,
            3,
            [$a, $b],
            [1 => 10, 2 => 20],
            [],
            [],
            null,
            [20 => true],
        );

        $byId = [];
        foreach ($lines as $line) {
            $byId[$line->pronosticId] = $line;
        }

        self::assertSame(1, $byId[1]->predHome);
        self::assertSame(1, $byId[1]->predAway);
        self::assertFalse($byId[1]->scoreInverted);
        self::assertSame(0, $byId[2]->predHome);
        self::assertSame(3, $byId[2]->predAway);
        self::assertTrue($byId[2]->scoreInverted);
        self::assertSame(3, $byId[2]->basePoints);
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

    private function pronostic(GameMatch $match, int $id, int $home, int $away): Pronostic
    {
        $user = (new User())->setEmail('u'.$id.'@t.local');
        (new \ReflectionProperty($user, 'id'))->setValue($user, $id);

        $pronostic = (new Pronostic())
            ->setMatch($match)
            ->setJoueur($user)
            ->setScoreDomicile($home)
            ->setScoreExterieur($away);
        (new \ReflectionProperty($pronostic, 'id'))->setValue($pronostic, $id);

        return $pronostic;
    }
}
