<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Repository\PronosticRepository;
use App\Repository\TeamMemberRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PronosticScoringService
{
    private const DEFAULT_POINTS_SCORE_EXACT = 3;
    private const DEFAULT_POINTS_BON_RESULTAT = 1;
    private const DEFAULT_POINTS_MAUVAIS_RESULTAT = 0;
    private const MAX_COTE_COEFFICIENT = 5.0;

    public function __construct(
        private readonly PronosticRepository $pronosticRepository,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly TeamRankingService $teamRankingService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function scorePronostic(Pronostic $pronostic): void
    {
        $match = $pronostic->getMatch();
        if ($match instanceof GameMatch) {
            $this->rescoreForMatch($match);
        }
    }

    public function rescoreForMatch(GameMatch $match): void
    {
        $pronostics = $this->pronosticRepository->findBy(['match' => $match]);
        $totalPronostics = count($pronostics);
        $occurrencesByScore = [];
        $riskByPronosticId = [];
        $coefficients = [];
        $playerTeamMap = $this->teamMemberRepository->findPlayerTeamMap();
        $teamScorePronostics = [];

        foreach ($pronostics as $pronostic) {
            $pronosticId = $pronostic->getId();
            $home = $pronostic->getScoreDomicile();
            $away = $pronostic->getScoreExterieur();
            if (null === $home || null === $away) {
                if (null !== $pronosticId) {
                    $riskByPronosticId[$pronosticId] = false;
                }
                continue;
            }
            $scoreKey = sprintf('%d-%d', $home, $away);
            $occurrencesByScore[$scoreKey] = ($occurrencesByScore[$scoreKey] ?? 0) + 1;

            $playerId = $pronostic->getJoueur()?->getId();
            $teamId = null !== $playerId ? ($playerTeamMap[$playerId] ?? null) : null;
            if (null !== $teamId && null !== $pronosticId) {
                $teamScorePronostics[$teamId][$scoreKey][] = $pronosticId;
            }
        }

        foreach ($teamScorePronostics as $scoresByTeam) {
            foreach ($scoresByTeam as $pronosticIds) {
                $isRisk = count($pronosticIds) >= 2;
                foreach ($pronosticIds as $pronosticId) {
                    $riskByPronosticId[$pronosticId] = $isRisk;
                }
            }
        }

        foreach ($pronostics as $pronostic) {
            $pronosticId = $pronostic->getId();
            $home = $pronostic->getScoreDomicile();
            $away = $pronostic->getScoreExterieur();
            if (null === $home || null === $away) {
                $pronostic
                    ->setPointsBase(null)
                    ->setCoteCoefficient(null)
                    ->setPoints(null)
                    ->setPriseRisque(false);
                continue;
            }

            $scoreKey = sprintf('%d-%d', $home, $away);
            $sameScoreCount = max(1, (int) ($occurrencesByScore[$scoreKey] ?? 1));
            $coefficientBrut = $totalPronostics > 0 ? ($totalPronostics / $sameScoreCount) : 1.0;
            $coefficient = round(min($coefficientBrut, self::MAX_COTE_COEFFICIENT), 2);
            $basePoints = $this->computeBasePoints($pronostic);
            $pointsFinaux = null !== $basePoints ? (float) round($basePoints * $coefficient) : null;

            $pronostic
                ->setPointsBase($basePoints)
                ->setCoteCoefficient($coefficient)
                ->setPoints($pointsFinaux)
                ->setPriseRisque(null !== $pronosticId ? ($riskByPronosticId[$pronosticId] ?? false) : false);

            $coefficients[] = $coefficient;
        }

        if ([] === $coefficients) {
            $match
                ->setCoteMin(null)
                ->setCoteMoyenne(null)
                ->setCoteMax(null);
        } else {
            $match
                ->setCoteMin(round(min($coefficients), 2))
                ->setCoteMoyenne(round(array_sum($coefficients) / count($coefficients), 2))
                ->setCoteMax(round(max($coefficients), 2));
        }

        $this->entityManager->flush();
        $this->teamRankingService->rebuildSnapshotsFromMatch($match);
    }

    private function computeBasePoints(Pronostic $pronostic): ?int
    {
        $match = $pronostic->getMatch();
        if (!$match instanceof GameMatch) {
            return null;
        }

        $scoreDomicileReel = $match->getScoreDomicile();
        $scoreExterieurReel = $match->getScoreExterieur();

        if (null === $scoreDomicileReel || null === $scoreExterieurReel) {
            return null;
        }

        $scoreDomicilePronostic = $pronostic->getScoreDomicile();
        $scoreExterieurPronostic = $pronostic->getScoreExterieur();

        if (null === $scoreDomicilePronostic || null === $scoreExterieurPronostic) {
            return null;
        }

        $pointsExact = $match->getPointsScoreExact() ?? self::DEFAULT_POINTS_SCORE_EXACT;
        $pointsBonResultat = $match->getPointsBonResultat() ?? self::DEFAULT_POINTS_BON_RESULTAT;
        $pointsMauvaisResultat = $match->getPointsMauvaisResultat() ?? self::DEFAULT_POINTS_MAUVAIS_RESULTAT;

        if ($scoreDomicilePronostic === $scoreDomicileReel && $scoreExterieurPronostic === $scoreExterieurReel) {
            return $pointsExact;
        }

        $resultatPronostic = $this->computeResultat($scoreDomicilePronostic, $scoreExterieurPronostic);
        $resultatReel = $this->computeResultat($scoreDomicileReel, $scoreExterieurReel);

        if ($resultatPronostic === $resultatReel) {
            return $pointsBonResultat;
        }

        return $pointsMauvaisResultat;
    }

    private function computeResultat(int $scoreDomicile, int $scoreExterieur): int
    {
        return $scoreDomicile <=> $scoreExterieur;
    }
}
