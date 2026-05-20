<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\But;
use App\Entity\Buteur;
use App\Entity\Country;
use App\Entity\GameMatch;
use App\Repository\ButRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Simulation manuelle d’un match (sans API-Football) : coup d’envoi, buts, fin.
 */
final class TestMatchManualSyncService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MatchKickoffService $matchKickoffService,
        private readonly PronosticScoringService $pronosticScoringService,
        private readonly ButeurGoalScoringService $buteurGoalScoringService,
        private readonly ButeurGoalNotificationService $buteurGoalNotificationService,
        private readonly Wc2026SyncService $wc2026SyncService,
        private readonly ButRepository $butRepository,
    ) {
    }

    public function resetPushReminder(GameMatch $match): void
    {
        $match->setPushReminderSentAt(null);
        $this->entityManager->flush();
    }

    public function kickoff(GameMatch $match): void
    {
        $this->assertManualTestMatch($match);

        $this->matchKickoffService->applyKickoff($match);
        $this->entityManager->flush();
    }

    /**
     * Enregistre un but, met à jour le score du match et recalcule les points prono.
     */
    public function registerGoal(
        GameMatch $match,
        Buteur $buteur,
        int $minute,
        bool $notify = true,
    ): But {
        $this->assertManualTestMatch($match);

        if ($minute < 0 || $minute > 130) {
            throw new \InvalidArgumentException('Minute invalide (0–130).');
        }

        $eventKey = sprintf('manual-m%d-b%d-%s', (int) $match->getId(), (int) $buteur->getId(), uniqid('', true));
        if ($this->butRepository->findOneBy(['apiSportsEventKey' => $eventKey]) instanceof But) {
            throw new \RuntimeException('But déjà enregistré (clé événement en doublon).');
        }

        $but = (new But())
            ->setButeur($buteur)
            ->setMatchRef($match)
            ->setMinute($minute)
            ->setApiSportsEventKey($eventKey);

        $this->buteurGoalScoringService->scoreBut($but);
        $this->entityManager->persist($but);

        $this->incrementMatchScoreForButeur($match, $buteur);
        $match->setLiveElapsedMinute($minute);

        if ('SCHEDULED' === $match->getStatut()) {
            $match->setStatut('LIVE');
            if (null === $match->getScoreDomicile() || null === $match->getScoreExterieur()) {
                $match->setScoreDomicile(0)->setScoreExterieur(0);
            }
        }

        $this->pronosticScoringService->rescoreForMatch($match);
        $this->buteurGoalScoringService->rescoreAll();

        if ($notify) {
            $this->buteurGoalNotificationService->notifyForNewBut($but);
        }

        $this->entityManager->flush();

        return $but;
    }

    public function finish(
        GameMatch $match,
        ?int $scoreDomicile = null,
        ?int $scoreExterieur = null,
    ): void {
        $this->assertManualTestMatch($match);

        if (null !== $scoreDomicile && null !== $scoreExterieur) {
            $match->setScoreDomicile($scoreDomicile)->setScoreExterieur($scoreExterieur);
        }

        if (null === $match->getScoreDomicile() || null === $match->getScoreExterieur()) {
            throw new \InvalidArgumentException('Scores finaux manquants : renseignez-les ou enregistrez des buts avant finish.');
        }

        $match->setStatut('FINISHED');
        $this->wc2026SyncService->finalizeMatchAfterFullTime($match);
        $this->entityManager->flush();
    }

    private function incrementMatchScoreForButeur(GameMatch $match, Buteur $buteur): void
    {
        $paysButeur = $buteur->getPays();
        if (!$paysButeur instanceof Country) {
            throw new \InvalidArgumentException(sprintf(
                'Le buteur %s %s n’a pas de pays associé.',
                (string) $buteur->getPrenom(),
                (string) $buteur->getNom(),
            ));
        }

        $home = $match->getPaysDomicile();
        $away = $match->getPaysExterieur();
        if (!$home instanceof Country || !$away instanceof Country) {
            throw new \InvalidArgumentException('Match sans pays domicile ou extérieur.');
        }

        $buteurPaysId = $paysButeur->getId();
        $homeId = $home->getId();
        $awayId = $away->getId();

        $scoreHome = $match->getScoreDomicile() ?? 0;
        $scoreAway = $match->getScoreExterieur() ?? 0;

        if (null !== $buteurPaysId && $buteurPaysId === $homeId) {
            $match->setScoreDomicile($scoreHome + 1);

            return;
        }

        if (null !== $buteurPaysId && $buteurPaysId === $awayId) {
            $match->setScoreExterieur($scoreAway + 1);

            return;
        }

        throw new \InvalidArgumentException(sprintf(
            'Le pays du buteur (%s) ne correspond ni au domicile (%s) ni à l’extérieur (%s).',
            (string) $paysButeur->getNom(),
            (string) $home->getNom(),
            (string) $away->getNom(),
        ));
    }

    private function assertManualTestMatch(GameMatch $match): void
    {
        if ($match->isApiFootballSyncEnabled() && null !== $match->getApiFootballFixtureId()) {
            throw new \InvalidArgumentException(
                'Ce match a la synchro API activée : désactivez « Synchro API-Football » pour le scénario manuel.'
            );
        }
    }
}
