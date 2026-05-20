<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\User;
use App\Service\MatchCotePreviewService;
use PHPUnit\Framework\TestCase;

final class MatchCotePreviewServiceTest extends TestCase
{
    private MatchCotePreviewService $service;

    protected function setUp(): void
    {
        $this->service = new MatchCotePreviewService();
    }

    public function testComputesMinMaxAndAverageFromPronostics(): void
    {
        $match = $this->createMatch();
        $pronostics = [
            $this->createPronostic(1, 0),
            $this->createPronostic(1, 0),
            $this->createPronostic(2, 1),
        ];

        $result = $this->service->computeForMatch($match, $pronostics);

        self::assertSame(3, $result['pronostics_count']);
        self::assertNotNull($result['moyenne']);
        self::assertNotNull($result['min']);
        self::assertNotNull($result['max']);
        self::assertLessThanOrEqual($result['max'], $result['moyenne']);
        self::assertGreaterThanOrEqual($result['min'], $result['moyenne']);
    }

    public function testReturnsNullCotesWhenNoPronostics(): void
    {
        $result = $this->service->computeForMatch($this->createMatch(), []);

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

        self::assertSame(1.5, $this->service->coefficientForScore(1, 0, $pronostics));
        self::assertSame(3.0, $this->service->coefficientForScore(2, 1, $pronostics));
    }

    public function testCoefficientForUnpredictedScoreUsesTotalPronos(): void
    {
        $pronostics = [
            $this->createPronostic(1, 0),
            $this->createPronostic(1, 0),
        ];

        self::assertSame(2.0, $this->service->coefficientForScore(3, 0, $pronostics));
    }

    public function testBuildDisplayContextForSimulatedScore(): void
    {
        $pronostics = [
            $this->createPronostic(1, 0),
            $this->createPronostic(2, 1),
        ];

        $context = $this->service->buildDisplayContext(2, 1, $pronostics);

        self::assertSame('2-1', $context['score_label']);
        self::assertSame(2.0, $context['for_score']);
        self::assertSame(2, $context['pronostics_count']);
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
