<?php

namespace App\Service;

use App\Entity\GameMatch;

final class MatchReminderMessageFactory
{
    private readonly MatchPushReminderPlanner $planner;

    public function __construct(?MatchPushReminderPlanner $planner = null)
    {
        $this->planner = $planner ?? new MatchPushReminderPlanner();
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function buildForMatch(GameMatch $match, \DateTimeImmutable $kickoff): array
    {
        return $this->buildForMatchday([$match]);
    }

    /**
     * Message e-mail regroupant tous les matchs oubliés d'une journée calendaire.
     *
     * @param list<GameMatch> $matches triés par coup d'envoi
     *
     * @return array{0: string, 1: string}
     */
    public function buildForMatchday(array $matches): array
    {
        $matches = array_values(array_filter(
            $matches,
            static fn (GameMatch $m): bool => $m->getDateHeure() instanceof \DateTimeImmutable,
        ));

        if ([] === $matches) {
            return ['Pronostic à faire', 'Tu n\'as pas encore pronostiqué. Pense à saisir tes pronos avant les matchs.'];
        }

        if (1 === \count($matches)) {
            $match = $matches[0];
            $kickoff = $match->getDateHeure();
            \assert($kickoff instanceof \DateTimeImmutable);

            return $this->buildSingleMatchMessage($match, $kickoff);
        }

        $tz = new \DateTimeZone(MatchPushReminderPlanner::TIMEZONE);
        $firstKickoff = $matches[0]->getDateHeure();
        \assert($firstKickoff instanceof \DateTimeImmutable);
        $dayLabel = $firstKickoff->setTimezone($tz)->format('d/m/Y');

        $lines = [];
        foreach ($matches as $match) {
            $kickoff = $match->getDateHeure();
            \assert($kickoff instanceof \DateTimeImmutable);
            $lines[] = '• '.$this->formatMatchLine($match, $kickoff);
        }

        return [
            'Pronostics à faire',
            sprintf(
                "Tu n'as pas encore pronostiqué pour les matchs du %s :\n%s",
                $dayLabel,
                implode("\n", $lines),
            ),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function buildSingleMatchMessage(GameMatch $match, \DateTimeImmutable $kickoff): array
    {
        $kickoffLocal = $kickoff->setTimezone(new \DateTimeZone(MatchPushReminderPlanner::TIMEZONE));
        $line = $this->formatMatchLine($match, $kickoff);

        if ($this->planner->isDayKickoff($kickoffLocal)) {
            return [
                'Pronostic à faire',
                sprintf('%s : tu n\'as pas encore pronostiqué.', $line),
            ];
        }

        return [
            'Pronostic à faire',
            sprintf('%s — pense à ton pronostic avant le match.', $line),
        ];
    }

    private function formatMatchLine(GameMatch $match, \DateTimeImmutable $kickoff): string
    {
        $kickoffLocal = $kickoff->setTimezone(new \DateTimeZone(MatchPushReminderPlanner::TIMEZONE));
        $domicile = $match->getPaysDomicile()?->getNom() ?? 'Équipe A';
        $exterieur = $match->getPaysExterieur()?->getNom() ?? 'Équipe B';
        $label = sprintf('%s — %s', $domicile, $exterieur);
        $timeStr = $kickoffLocal->format('H\\hi');

        return sprintf('%s (coup d\'envoi à %s)', $label, $timeStr);
    }
}
