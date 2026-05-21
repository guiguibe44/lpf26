<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\User;
use App\Service\MatchCoteOneNTwoCalculator;
use App\Service\MatchOutcomeResolver;
use PHPUnit\Framework\TestCase;

final class MatchCoteOneNTwoCalculatorTest extends TestCase
{
    private MatchCoteOneNTwoCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new MatchCoteOneNTwoCalculator(new MatchOutcomeResolver());
    }

    public function testSharedDrawOddsForDifferentDrawScores(): void
    {
        $pronostics = [
            $this->createPronostic(2, 2),
            $this->createPronostic(1, 1),
        ];

        $overview = $this->calculator->computeOverview($pronostics);

        self::assertSame(1.0, $overview['draw']);
        self::assertSame(1.0, $this->calculator->coefficientForPredictedScore(2, 2, $pronostics));
        self::assertSame(1.0, $this->calculator->coefficientForPredictedScore(1, 1, $pronostics));
    }

    public function testGoodDrawResultUsesDrawOdds(): void
    {
        $match = $this->createMatch();
        $pronostics = [
            $this->createPronostic(2, 2),
            $this->createPronostic(1, 0),
        ];

        $coef = $this->calculator->coefficientForPronosticLine(
            2,
            2,
            1,
            1,
            1,
            $match->getPointsScoreExact() ?? 3,
            $match->getPointsBonResultat() ?? 1,
            $match->getPointsMauvaisResultat() ?? 0,
            $pronostics,
        );

        self::assertSame(1.5, $coef);
    }

    public function testWrongPredictionUsesPredictedOutcomeOdds(): void
    {
        $match = $this->createMatch();
        $pronostics = [
            $this->createPronostic(2, 0),
            $this->createPronostic(3, 1),
            $this->createPronostic(1, 1),
        ];

        $homeOdds = $this->calculator->computeMatchOdds($pronostics)['HOME'];

        $coef = $this->calculator->coefficientForPronosticLine(
            2,
            0,
            0,
            1,
            0,
            $match->getPointsScoreExact() ?? 3,
            $match->getPointsBonResultat() ?? 1,
            $match->getPointsMauvaisResultat() ?? 0,
            $pronostics,
        );

        self::assertSame($homeOdds, $coef);
    }

    public function testRoundsToHalfStep(): void
    {
        self::assertSame(3.5, $this->calculator->calculateOddsForOutcomeCount(4, 1));
    }

    private function createMatch(): GameMatch
    {
        return (new GameMatch())
            ->setPaysDomicile((new Country())->setNom('A'))
            ->setPaysExterieur((new Country())->setNom('B'))
            ->setDateHeure(new \DateTimeImmutable());
    }

    private function createPronostic(int $home, int $away): Pronostic
    {
        return (new Pronostic())
            ->setJoueur(new User())
            ->setMatch($this->createMatch())
            ->setScoreDomicile($home)
            ->setScoreExterieur($away);
    }
}
