<?php

namespace App\Service;

/**
 * Calcule l'heure d'envoi des relances pronostic :
 * - match entre 10h et minuit (heure locale) → 1 h avant le coup d'envoi ;
 * - match entre 0h et 10h → 22 h la veille (heure locale).
 */
final class MatchPushReminderPlanner
{
    public const string TIMEZONE = 'Europe/Paris';

    private const int DAY_KICKOFF_HOUR_MIN = 10;

    private const int NIGHT_REMINDER_HOUR = 22;

    public function getReminderAt(\DateTimeImmutable $kickoff): \DateTimeImmutable
    {
        $kickoffLocal = $kickoff->setTimezone($this->timezone());

        if ($this->isDayKickoff($kickoffLocal)) {
            return $kickoffLocal->sub(new \DateInterval('PT1H'));
        }

        return $kickoffLocal->modify('-1 day')->setTime(self::NIGHT_REMINDER_HOUR, 0);
    }

    /**
     * La relance est due dès l'heure prévue, tant que le match n'a pas commencé.
     */
    public function isReminderDue(\DateTimeImmutable $kickoff, \DateTimeImmutable $now): bool
    {
        if ($now >= $kickoff) {
            return false;
        }

        return $now >= $this->getReminderAt($kickoff);
    }

    public function isDayKickoff(\DateTimeImmutable $kickoffLocal): bool
    {
        return (int) $kickoffLocal->format('G') >= self::DAY_KICKOFF_HOUR_MIN;
    }

    private function timezone(): \DateTimeZone
    {
        return new \DateTimeZone(self::TIMEZONE);
    }
}
