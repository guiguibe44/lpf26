<?php

namespace App\Service;

use App\Entity\GameMatch;

final class MatchReminderMessageFactory
{
    /**
     * @return array{0: string, 1: string}
     */
    public function buildForMatch(GameMatch $match, \DateTimeImmutable $kickoff): array
    {
        $kickoffLocal = $kickoff->setTimezone(new \DateTimeZone(MatchPushReminderPlanner::TIMEZONE));
        $domicile = $match->getPaysDomicile()?->getNom() ?? 'Équipe A';
        $exterieur = $match->getPaysExterieur()?->getNom() ?? 'Équipe B';
        $label = sprintf('%s — %s', $domicile, $exterieur);
        $timeStr = $kickoffLocal->format('H\\hi');

        $planner = new MatchPushReminderPlanner();
        if ($planner->isDayKickoff($kickoffLocal)) {
            return [
                'Pronostic à faire',
                sprintf('%s : coup d\'envoi à %s, tu n\'as pas encore pronostiqué.', $label, $timeStr),
            ];
        }

        return [
            'Pronostic à faire',
            sprintf('%s (%s) — pense à ton pronostic avant le match.', $label, $timeStr),
        ];
    }
}
