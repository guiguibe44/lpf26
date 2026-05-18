<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Repository\PronosticRepository;
use App\Repository\TeamJokerUsageRepository;
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
        private readonly DefaultPronosticService $defaultPronosticService,
        private readonly PronosticSimulationService $pronosticSimulationService,
        private readonly TeamJokerUsageRepository $teamJokerUsageRepository,
        private readonly JokerScoringApplicator $jokerScoringApplicator,
        private readonly JokerStealPointsService $jokerStealPointsService,
        private readonly PronosticScoreInversionService $pronosticScoreInversionService,
        private readonly JokerCollectePointsService $jokerCollectePointsService,
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
        $this->defaultPronosticService->ensureDefaultsForMatch($match);

        $pronostics = $this->pronosticRepository->findBy(['match' => $match]);
        $totalPronostics = count($pronostics);
        $occurrencesByScore = [];
        $riskByPronosticId = [];
        $coefficients = [];
        $playerTeamMap = $this->teamMemberRepository->findPlayerTeamMap();
        $teamScorePronostics = [];
        $invertedTargetTeamIds = $this->pronosticScoreInversionService->getTargetTeamIdsForMatch($match);
        $effectiveByPronosticId = $this->pronosticScoreInversionService->buildEffectiveScoresByPronosticId(
            $pronostics,
            $playerTeamMap,
            $invertedTargetTeamIds,
        );

        foreach ($pronostics as $pronostic) {
            $pronosticId = $pronostic->getId();
            if (null === $pronosticId || !isset($effectiveByPronosticId[$pronosticId])) {
                if (null !== $pronosticId) {
                    $riskByPronosticId[$pronosticId] = false;
                }
                continue;
            }

            $effective = $effectiveByPronosticId[$pronosticId];
            $home = $effective['home'];
            $away = $effective['away'];
            $scoreKey = sprintf('%d-%d', $home, $away);
            $occurrencesByScore[$scoreKey] = ($occurrencesByScore[$scoreKey] ?? 0) + 1;

            $playerId = $pronostic->getJoueur()?->getId();
            $teamId = null !== $playerId ? ($playerTeamMap[$playerId] ?? null) : null;
            if (null !== $teamId) {
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

        $jokerCodeByTeamId = $this->teamJokerUsageRepository->findJokerCodesByTeamForMatch($match);
        $realHome = $match->getScoreDomicile();
        $realAway = $match->getScoreExterieur();
        $hasFinalScore = null !== $realHome && null !== $realAway;
        foreach ($pronostics as $pronostic) {
            $pronosticId = $pronostic->getId();
            if (null === $pronosticId || !isset($effectiveByPronosticId[$pronosticId])) {
                $pronostic
                    ->setPointsBase(null)
                    ->setCoteCoefficient(null)
                    ->setPoints(null)
                    ->setPointsEquipe(null)
                    ->setPriseRisque(false);
                continue;
            }

            $effective = $effectiveByPronosticId[$pronosticId];
            $home = $effective['home'];
            $away = $effective['away'];
            $scoreKey = sprintf('%d-%d', $home, $away);
            $sameScoreCount = max(1, (int) ($occurrencesByScore[$scoreKey] ?? 1));
            $coefficientBrut = $totalPronostics > 0 ? ($totalPronostics / $sameScoreCount) : 1.0;
            $coefficient = round(min($coefficientBrut, self::MAX_COTE_COEFFICIENT), 2);
            $basePoints = $hasFinalScore
                ? $this->pronosticSimulationService->computeBasePoints($match, $realHome, $realAway, $home, $away)
                : null;
            $pointsFinaux = null !== $basePoints ? (float) round($basePoints * $coefficient) : null;

            $playerId = $pronostic->getJoueur()?->getId();
            $teamId = null !== $playerId ? ($playerTeamMap[$playerId] ?? null) : null;
            $jokerCode = null !== $teamId ? ($jokerCodeByTeamId[$teamId] ?? null) : null;
            $jokerPoints = $hasFinalScore && null !== $pointsFinaux
                ? $this->jokerScoringApplicator->applyForTeam(
                    $jokerCode,
                    $match,
                    $realHome,
                    $realAway,
                    $home,
                    $away,
                    $pointsFinaux,
                    $coefficient,
                )
                : null;

            if (null !== $jokerPoints) {
                $pronostic
                    ->setPoints($jokerPoints['playerPoints'])
                    ->setPointsEquipe($jokerPoints['teamPoints']);
            } else {
                $pronostic
                    ->setPoints($pointsFinaux)
                    ->setPointsEquipe(null);
            }

            $pronostic
                ->setPointsBase($basePoints)
                ->setCoteCoefficient($coefficient)
                ->setPriseRisque(null !== $pronosticId ? ($riskByPronosticId[$pronosticId] ?? false) : false);

            $coefficients[] = $coefficient;
        }

        if ($hasFinalScore) {
            $this->jokerStealPointsService->applyToPronostics($match, $pronostics, $playerTeamMap);
            $this->jokerCollectePointsService->applyToPronostics($match, $pronostics, $playerTeamMap);
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
}
