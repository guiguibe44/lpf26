<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Buteur;
use App\Entity\But;
use App\Repository\ButRepository;
use App\Repository\UserRepository;
use App\Service\ButeurGoalScoringService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ButeurGoalScoringServiceTest extends TestCase
{
    #[DataProvider('tierProvider')]
    public function testPointsPerGoalForSelections(int $selections, int $expectedPoints, float $expectedCoefficient): void
    {
        $service = $this->createService(selectionsForButeur: max(1, $selections));

        self::assertSame($expectedPoints, $service->getPointsPerGoalForSelections($selections));
    }

    public static function tierProvider(): iterable
    {
        yield 'solo' => [1, 50, 5.0];
        yield 'duo' => [2, 40, 4.0];
        yield 'trio' => [3, 30, 3.0];
        yield 'quatuor' => [4, 30, 3.0];
        yield 'cinq' => [5, 20, 2.0];
        yield 'sept' => [7, 20, 2.0];
        yield 'huit' => [8, 10, 1.0];
        yield 'tres populaire' => [12, 10, 1.0];
        yield 'aucun choix' => [0, 10, 1.0];
    }

    public function testScoreButAppliesTierPoints(): void
    {
        $buteur = (new Buteur())->setNom('Rare')->setPrenom('Joueur');
        $but = (new But())->setButeur($buteur);
        $service = $this->createService(selectionsForButeur: 1);

        $service->scoreBut($but);

        self::assertSame(10, $but->getPointsBase());
        self::assertSame(5.0, $but->getCoteCoefficient());
        self::assertSame(50, $but->getPointsAttribues());
    }

    public function testPopularButeurGetsTenPointsPerGoal(): void
    {
        $buteur = (new Buteur())->setNom('Mbappé')->setPrenom('Kylian');
        $service = $this->createService(selectionsForButeur: 8);

        self::assertSame(10, $service->getPointsPerGoalForButeur($buteur));
        self::assertSame(1.0, $service->getCurrentCoefficientForButeur($buteur));
    }

    private function createService(int $selectionsForButeur): ButeurGoalScoringService
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('countWithButeurChoisiId')->willReturn($selectionsForButeur);

        $butRepository = $this->createMock(ButRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        return new ButeurGoalScoringService($userRepository, $butRepository, $em);
    }
}
