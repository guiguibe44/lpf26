<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Buteur;
use App\Entity\But;
use App\Repository\ButRepository;
use App\Repository\UserRepository;
use App\Service\ButeurGoalScoringService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ButeurGoalScoringServiceTest extends TestCase
{
    public function testPopularButeurGetsLowerCoefficient(): void
    {
        $buteur = (new Buteur())->setNom('Mbappé')->setPrenom('Kylian');
        $service = $this->createService(totalWithButeur: 20, selectionsForButeur: 10);

        self::assertSame(2.0, $service->getCurrentCoefficientForButeur($buteur));
    }

    public function testRareButeurGetsHigherCoefficientCappedAtFive(): void
    {
        $buteur = (new Buteur())->setNom('Rare')->setPrenom('Joueur');
        $service = $this->createService(totalWithButeur: 20, selectionsForButeur: 1);

        self::assertSame(5.0, $service->getCurrentCoefficientForButeur($buteur));
    }

    public function testScoreButAppliesBaseTimesCoefficient(): void
    {
        $buteur = (new Buteur())->setNom('Test')->setPrenom('A');
        $but = (new But())->setButeur($buteur);
        $service = $this->createService(totalWithButeur: 10, selectionsForButeur: 2);

        $service->scoreBut($but);

        self::assertSame(1, $but->getPointsBase());
        self::assertSame(5.0, $but->getCoteCoefficient());
        self::assertSame(5, $but->getPointsAttribues());
    }

    private function createService(int $totalWithButeur, int $selectionsForButeur): ButeurGoalScoringService
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('countWithButeurChoisi')->willReturn($totalWithButeur);
        $userRepository->method('countWithButeurChoisiId')->willReturn($selectionsForButeur);

        $butRepository = $this->createMock(ButRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        return new ButeurGoalScoringService($userRepository, $butRepository, $em);
    }
}
