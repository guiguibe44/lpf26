<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\But;
use App\Entity\Buteur;
use App\Repository\ButRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Points par but du buteur choisi : paliers fixes selon la popularité du choix.
 *
 *  1 joueur  → 50 pts | 2 → 40 | 3-4 → 30 | 5-7 → 20 | 8+ → 10
 */
final class ButeurGoalScoringService
{
    public const int DEFAULT_POINTS_BASE = 10;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ButRepository $butRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /** Coefficient affiché (pts ÷ 10) : ×5 = 50 pts, ×1 = 10 pts. */
    public function getCurrentCoefficientForButeur(Buteur $buteur): float
    {
        $selections = $this->countSelectionsForButeur($buteur);

        return round($this->getPointsPerGoalForSelections($selections) / self::DEFAULT_POINTS_BASE, 2);
    }

    public function getPointsPerGoalForButeur(Buteur $buteur): int
    {
        return $this->getPointsPerGoalForSelections($this->countSelectionsForButeur($buteur));
    }

    public function getPointsPerGoalForSelections(int $selections): int
    {
        return match (true) {
            $selections <= 0 => 10,
            1 === $selections => 50,
            2 === $selections => 40,
            $selections <= 4 => 30,
            $selections <= 7 => 20,
            default => 10,
        };
    }

    public function scoreBut(But $but): void
    {
        $buteur = $but->getButeur();
        if (!$buteur instanceof Buteur) {
            $but
                ->setPointsBase(self::DEFAULT_POINTS_BASE)
                ->setCoteCoefficient(null)
                ->setPointsAttribues(0);

            return;
        }

        $points = $this->getPointsPerGoalForButeur($buteur);
        $coefficient = round($points / self::DEFAULT_POINTS_BASE, 2);

        $but
            ->setPointsBase(self::DEFAULT_POINTS_BASE)
            ->setCoteCoefficient($coefficient)
            ->setPointsAttribues($points);
    }

    public function rescoreAll(): void
    {
        foreach ($this->butRepository->findAll() as $but) {
            $this->scoreBut($but);
        }

        $this->entityManager->flush();
    }

    public function rescoreForButeur(Buteur $buteur): void
    {
        foreach ($this->butRepository->findBy(['buteur' => $buteur]) as $but) {
            $this->scoreBut($but);
        }

        $this->entityManager->flush();
    }

    private function countSelectionsForButeur(Buteur $buteur): int
    {
        return $this->userRepository->countWithButeurChoisiId((int) $buteur->getId());
    }
}
