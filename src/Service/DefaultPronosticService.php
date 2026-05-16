<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;
use App\Entity\Pronostic;
use App\Entity\User;
use App\Repository\GameMatchRepository;
use App\Repository\PronosticRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class DefaultPronosticService
{
    public function __construct(
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly PronosticRepository $pronosticRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Crée un pronostic 0-0 pour chaque joueur cotisé qui n'en a pas encore sur ce match.
     */
    public function ensureDefaultsForMatch(GameMatch $match): void
    {
        if ($this->canStillEditPronostic($match, new \DateTimeImmutable())) {
            return;
        }

        $users = $this->userRepository->findPlayersWithoutPronosticForMatch($match);
        if ([] === $users) {
            return;
        }

        foreach ($users as $user) {
            $pronostic = new Pronostic();
            $pronostic->setJoueur($user);
            $pronostic->setMatch($match);
            $this->entityManager->persist($pronostic);
        }

        $this->entityManager->flush();
    }

    /**
     * Applique le 0-0 par défaut pour les matchs déjà verrouillés (kickoff passé ou statut non programmé).
     *
     * @param list<GameMatch> $matches
     */
    public function ensureDefaultsForUser(User $user, array $matches = []): void
    {
        if (!$user->isCotisationPayee()) {
            return;
        }

        if ([] === $matches) {
            $matches = $this->gameMatchRepository->findBy([], ['dateHeure' => 'ASC']);
        }

        $now = new \DateTimeImmutable();
        $matchesToLock = [];
        foreach ($matches as $match) {
            if (!$match instanceof GameMatch || $this->canStillEditPronostic($match, $now)) {
                continue;
            }
            $matchesToLock[] = $match;
        }

        if ([] === $matchesToLock) {
            return;
        }

        $existing = $this->pronosticRepository->findIndexedByPlayerAndMatches($user, $matchesToLock);
        $created = false;
        foreach ($matchesToLock as $match) {
            $matchId = $match->getId();
            if (null === $matchId || isset($existing[$matchId])) {
                continue;
            }

            $pronostic = new Pronostic();
            $pronostic->setJoueur($user);
            $pronostic->setMatch($match);
            $this->entityManager->persist($pronostic);
            $created = true;
        }

        if ($created) {
            $this->entityManager->flush();
        }
    }

    /**
     * Applique le 0-0 par défaut pour tous les joueurs cotisés sur tous les matchs verrouillés.
     *
     * @return list<GameMatch> matchs ayant reçu au moins un nouveau pronostic
     */
    public function ensureDefaultsForAllPayingPlayers(): array
    {
        $users = $this->userRepository->findActivePlayersOrderedByEmail();
        $matches = $this->gameMatchRepository->findBy([], ['dateHeure' => 'ASC']);
        $now = new \DateTimeImmutable();
        $matchesWithNewPronostics = [];

        foreach ($users as $user) {
            $existing = $this->pronosticRepository->findIndexedByPlayerAndMatches($user, $matches);
            foreach ($matches as $match) {
                if (!$match instanceof GameMatch || $this->canStillEditPronostic($match, $now)) {
                    continue;
                }
                $matchId = $match->getId();
                if (null === $matchId || isset($existing[$matchId])) {
                    continue;
                }

                $pronostic = new Pronostic();
                $pronostic->setJoueur($user);
                $pronostic->setMatch($match);
                $this->entityManager->persist($pronostic);
                $existing[$matchId] = $pronostic;
                $matchesWithNewPronostics[$matchId] = $match;
            }
        }

        if ([] === $matchesWithNewPronostics) {
            return [];
        }

        $this->entityManager->flush();

        return array_values($matchesWithNewPronostics);
    }

    private function canStillEditPronostic(GameMatch $match, \DateTimeImmutable $now): bool
    {
        $dateHeure = $match->getDateHeure();

        return 'SCHEDULED' === $match->getStatut()
            && null !== $dateHeure
            && $dateHeure > $now;
    }
}
