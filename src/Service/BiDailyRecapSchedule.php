<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Récap d’équipe : envoi tous les 2 jours vers 9 h 30 (Europe/Paris).
 */
final class BiDailyRecapSchedule
{
    public const string TIMEZONE = 'Europe/Paris';

    public const int INTERVAL_DAYS = 2;

    public const string SEND_TIME = '09:30';

    public function isSendWindowOpen(\DateTimeImmutable $now): bool
    {
        $paris = $now->setTimezone(new \DateTimeZone(self::TIMEZONE));
        $current = $paris->format('H:i');

        return $current >= self::SEND_TIME;
    }

    public function shouldSendNow(\DateTimeImmutable $now, ?\DateTimeImmutable $lastSentAt, bool $force): bool
    {
        if ($force) {
            return true;
        }

        if (!$this->isSendWindowOpen($now)) {
            return false;
        }

        if (null === $lastSentAt) {
            return true;
        }

        $elapsed = $now->getTimestamp() - $lastSentAt->getTimestamp();

        return $elapsed >= self::INTERVAL_DAYS * 86_400;
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} periodStart (inclus), periodEnd (exclus)
     */
    public function resolvePeriod(\DateTimeImmutable $now, ?\DateTimeImmutable $lastPeriodEnd): array
    {
        $tz = new \DateTimeZone(self::TIMEZONE);
        $periodEnd = $now->setTimezone($tz)->modify('today');
        $periodStart = $lastPeriodEnd ?? $periodEnd->modify(sprintf('-%d days', self::INTERVAL_DAYS));

        return [$periodStart, $periodEnd];
    }

    public function formatPeriodLabel(\DateTimeImmutable $start, \DateTimeImmutable $end): string
    {
        $tz = new \DateTimeZone(self::TIMEZONE);
        $startParis = $start->setTimezone($tz);
        $endParis = $end->setTimezone($tz)->modify('-1 day');

        if ($startParis->format('Y-m-d') === $endParis->format('Y-m-d')) {
            return $startParis->format('d/m/Y');
        }

        if ($startParis->format('Y') === $endParis->format('Y') && $startParis->format('m') === $endParis->format('m')) {
            return sprintf(
                '%s au %s %s %s',
                $startParis->format('j'),
                $endParis->format('j'),
                self::frenchMonth((int) $endParis->format('n')),
                $endParis->format('Y'),
            );
        }

        return sprintf('%s au %s', $startParis->format('d/m/Y'), $endParis->format('d/m/Y'));
    }

    private static function frenchMonth(int $month): string
    {
        return match ($month) {
            1 => 'janvier',
            2 => 'février',
            3 => 'mars',
            4 => 'avril',
            5 => 'mai',
            6 => 'juin',
            7 => 'juillet',
            8 => 'août',
            9 => 'septembre',
            10 => 'octobre',
            11 => 'novembre',
            12 => 'décembre',
            default => '',
        };
    }
}
