<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Repository\GameMatchRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Coup d’envoi automatique : matchs encore « Programmé » dont l’heure est passée → LIVE 0-0 + pronos 0-0 par défaut.
 */
final class MatchKickoffService
{
    public function __construct(
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly UserRepository $userRepository,
        private readonly DefaultPronosticService $defaultPronosticService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{matchesChecked: int, matchesStarted: int, pronosticsCreated: int}
     */
    public function processDueKickoffs(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $matches = $this->gameMatchRepository->findScheduledPastKickoff($now);

        $summary = [
            'matchesChecked' => \count($matches),
            'matchesStarted' => 0,
            'pronosticsCreated' => 0,
        ];

        foreach ($matches as $match) {
            if (!$match instanceof GameMatch) {
                continue;
            }

            $withoutProno = \count($this->userRepository->findPlayersWithoutPronosticForMatch($match));
            $this->applyKickoff($match);

            ++$summary['matchesStarted'];
            $summary['pronosticsCreated'] += max(
                0,
                $withoutProno - \count($this->userRepository->findPlayersWithoutPronosticForMatch($match)),
            );
        }

        if ($summary['matchesStarted'] > 0) {
            $this->entityManager->flush();
        }

        return $summary;
    }

    public function applyKickoff(GameMatch $match): void
    {
        if ('SCHEDULED' !== $match->getStatut()) {
            return;
        }

        $kickoff = $match->getDateHeure();
        if (!$kickoff instanceof \DateTimeImmutable || $kickoff > new \DateTimeImmutable()) {
            return;
        }

        $match
            ->setStatut('LIVE')
            ->setLiveElapsedMinute(0);

        if (null === $match->getScoreDomicile() || null === $match->getScoreExterieur()) {
            $match->setScoreDomicile(0)->setScoreExterieur(0);
        }

        $this->defaultPronosticService->ensureDefaultsForMatch($match);
    }

    /** Coup d’envoi manuel (scénario test) sans attendre l’heure du match. */
    public function applyKickoffForced(GameMatch $match): void
    {
        if ('SCHEDULED' !== $match->getStatut()) {
            return;
        }

        $match
            ->setStatut('LIVE')
            ->setLiveElapsedMinute(0);

        if (null === $match->getScoreDomicile() || null === $match->getScoreExterieur()) {
            $match->setScoreDomicile(0)->setScoreExterieur(0);
        }

        $this->defaultPronosticService->ensureDefaultsForMatch($match);
    }
}
