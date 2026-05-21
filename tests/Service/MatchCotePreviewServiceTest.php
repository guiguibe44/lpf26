<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\User;
use App\Service\MatchCoteExactScoreCalculator;
use App\Service\MatchCoteOneNTwoCalculator;
use App\Service\MatchCotePreviewService;
use App\Service\MatchCoteService;
use App\Service\MatchOutcomeResolver;
use PHPUnit\Framework\TestCase;

final class MatchCotePreviewServiceTest extends TestCase
{
    private MatchCotePreviewService $serviceExactScore;

    private MatchCotePreviewService $serviceOneNTwo;

    protected function setUp(): void
    {
        $outcome = new MatchOutcomeResolver();
        $exact = new MatchCoteExactScoreCalculator();
        $oneNTwo = new MatchCoteOneNTwoCalculator($outcome);

        $this->serviceExactScore = new MatchCotePreviewService(
            new MatchCoteService('exact_score', $oneNTwo, $exact, $outcome),
        );
        $this->serviceOneNTwo = new MatchCotePreviewService(
            new MatchCoteService('one_n_two', $oneNTwo, $exact, $outcome),
        );
    }

    public function testComputesMinMaxAndAverageFromPronostics(): void
    {
        $match = $this->createMatch();
        $pronostics = [
            $this->createPronostic(1, 0),
            $this->createPronostic(1, 0),
            $this->createPronostic(2, 1),
        ];

        $result = $this->serviceExactScore->computeForMatch($match, $pronostics);

        self::assertSame(3, $result['pronostics_count']);
        self::assertNotNull($result['moyenne']);
        self::assertNotNull($result['min']);
        self::assertNotNull($result['max']);
        self::assertLessThanOrEqual($result['max'], $result['moyenne']);
        self::assertGreaterThanOrEqual($result['min'], $result['moyenne']);
    }

    public function testReturnsNullCotesWhenNoPronostics(): void
    {
        $result = $this->serviceExactScore->computeForMatch($this->createMatch(), []);

        self::assertSame(0, $result['pronostics_count']);
        self::assertNull($result['moyenne']);
    }

    public function testCoefficientForScoreMatchesPronosticCount(): void
    {
        $pronostics = [
            $this->createPronostic(1, 0),
            $this->createPronostic(1, 0),
            $this->createPronostic(2, 1),
        ];

        self::assertSame(1.5, $this->serviceExactScore->coefficientForScore(1, 0, $pronostics));
        self::assertSame(3.0, $this->serviceExactScore->coefficientForScore(2, 1, $pronostics));
    }

    public function testCoefficientForUnpredictedScoreUsesTotalPronos(): void
    {
        $pronostics = [
            $this->createPronostic(1, 0),
            $this->createPronostic(1, 0),
        ];

        self::assertSame(2.0, $this->serviceExactScore->coefficientForScore(3, 0, $pronostics));
    }

    public function testBuildDisplayContextForSimulatedScore(): void
    {
        $pronostics = [
            $this->createPronostic(1, 0),
            $this->createPronostic(2, 1),
        ];

        $context = $this->serviceExactScore->buildDisplayContext(2, 1, $pronostics);

        self::assertSame('2-1', $context['score_label']);
        self::assertSame(2.0, $context['for_score']);
        self::assertSame(2, $context['pronostics_count']);
    }

    public function testOneNTwoOverviewExposesHomeDrawAway(): void
    {
        $pronostics = [
            $this->createPronostic(2, 2),
            $this->createPronostic(1, 0),
        ];

        $result = $this->serviceOneNTwo->computeForMatch($this->createMatch(), $pronostics);

        self::assertSame('one_n_two', $result['mode']);
        self::assertNotNull($result['home']);
        self::assertNotNull($result['draw']);
        self::assertNotNull($result['away']);
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
