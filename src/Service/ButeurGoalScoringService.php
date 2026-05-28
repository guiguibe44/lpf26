<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\But;
use App\Entity\Buteur;
use App\Repository\ButRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Points par but marqué par le buteur choisi : arrondi(10 × cote de popularité à 2 décimales).
 * Cote = joueurs avec buteur / joueurs l’ayant choisi (plafond ×5).
 */
final class ButeurGoalScoringService
{
    public const int DEFAULT_POINTS_BASE = 10;

    public const float MAX_COTE_COEFFICIENT = 5.0;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ButRepository $butRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getCurrentCoefficientForButeur(Buteur $buteur): float
    {
        return $this->computeCoefficientForButeur($buteur);
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

        $coefficient = $this->computeCoefficientForButeur($buteur);
        $base = self::DEFAULT_POINTS_BASE;
        $points = (int) round($base * $coefficient);

        $but
            ->setPointsBase($base)
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

    private function computeCoefficientForButeur(Buteur $buteur): float
    {
        $totalWithButeur = $this->userRepository->countWithButeurChoisi();
        $selectionsForButeur = $this->userRepository->countWithButeurChoisiId((int) $buteur->getId());

        if ($totalWithButeur <= 0 || $selectionsForButeur <= 0) {
            return 1.0;
        }

        $coefficientBrut = $totalWithButeur / max(1, $selectionsForButeur);

        return round(min($coefficientBrut, self::MAX_COTE_COEFFICIENT), 2);
    }
}
