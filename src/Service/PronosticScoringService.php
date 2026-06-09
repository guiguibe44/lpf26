<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Repository\PronosticRepository;
use App\Repository\TeamJokerUsageRepository;
use App\Repository\TeamMemberRepository;
use App\Security\SuperAdminAuthorization;
use App\Service\Badge\BadgeEvaluator;
use Doctrine\ORM\EntityManagerInterface;

final class PronosticScoringService
{
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
        private readonly MatchCoteService $matchCoteService,
        private readonly BadgeEvaluator $badgeEvaluator,
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

        $allPronostics = $this->pronosticRepository->findBy(['match' => $match]);
        $pronostics = SuperAdminAuthorization::filterCompetitionPronostics($allPronostics);
        $occurrencesByScore = [];
        $riskByPronosticId = [];
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
        foreach ($allPronostics as $pronostic) {
            $pronosticId = $pronostic->getId();
            if (SuperAdminAuthorization::excludesFromCompetitionPronostics($pronostic->getJoueur())
                || null === $pronosticId
                || !isset($effectiveByPronosticId[$pronosticId])) {
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
            $basePoints = $hasFinalScore
                ? $this->pronosticSimulationService->computeBasePoints($match, $realHome, $realAway, $home, $away)
                : null;
            $coefficient = null;
            if ($hasFinalScore && null !== $basePoints) {
                $coefficient = $this->matchCoteService->coefficientForPronosticLine(
                    $match,
                    $home,
                    $away,
                    $realHome,
                    $realAway,
                    $basePoints,
                    $pronostics,
                ) ?? 1.0;
            }
            $pointsFinaux = null !== $basePoints && null !== $coefficient
                ? (float) round($basePoints * $coefficient)
                : null;

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
        }

        if ($hasFinalScore) {
            $this->jokerStealPointsService->applyToPronostics($match, $pronostics, $playerTeamMap);
            $this->jokerCollectePointsService->applyToPronostics($match, $pronostics, $playerTeamMap);
        }

        $this->matchCoteService->persistMatchOdds($match, $pronostics);

        $this->entityManager->flush();
        $this->teamRankingService->rebuildSnapshotsFromMatch($match);
        $this->badgeEvaluator->evaluateAfterMatchRescore($match);
    }
}
