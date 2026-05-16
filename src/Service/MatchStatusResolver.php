<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GameMatch;

final class MatchStatusResolver
{
    private const LIVE_STATUSES = [
        'LIVE',
        '1H',
        '2H',
        'HT',
        'ET',
        'BT',
        'INT',
        'P',
    ];

    public function isMatchFinished(GameMatch $match, ?\DateTimeImmutable $now = null): bool
    {
        $statut = $match->getStatut();
        if ('FINISHED' === $statut || 'CANCELLED' === $statut) {
            return true;
        }

        if (\in_array($statut, self::LIVE_STATUSES, true)) {
            return false;
        }

        $now ??= new \DateTimeImmutable();
        $dateHeure = $match->getDateHeure();
        $hasScores = null !== $match->getScoreDomicile() && null !== $match->getScoreExterieur();

        // Coup d'envoi passé mais encore « Programmé » : match en cours jusqu'au statut Terminé.
        if ('SCHEDULED' === $statut && $dateHeure instanceof \DateTimeImmutable && $dateHeure <= $now) {
            return false;
        }

        return $hasScores
            && $dateHeure instanceof \DateTimeImmutable
            && $dateHeure < $now;
    }

    public function isMatchStarted(GameMatch $match, ?\DateTimeImmutable $now = null): bool
    {
        if ($this->isMatchFinished($match, $now)) {
            return true;
        }

        if (\in_array($match->getStatut(), self::LIVE_STATUSES, true)) {
            return true;
        }

        $dateHeure = $match->getDateHeure();
        $now ??= new \DateTimeImmutable();

        return null !== $dateHeure && $dateHeure <= $now;
    }

    public function isMatchLive(GameMatch $match, ?\DateTimeImmutable $now = null): bool
    {
        return $this->isMatchStarted($match, $now) && !$this->isMatchFinished($match, $now);
    }
}
