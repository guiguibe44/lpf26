<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('fr_date_long', [$this, 'formatDateLong']),
            new TwigFilter('fr_datetime', [$this, 'formatDateTime']),
        ];
    }

    public function formatDateLong(?\DateTimeInterface $date): string
    {
        if (null === $date) {
            return '';
        }

        $formatter = new \IntlDateFormatter(
            'fr_FR',
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            $date->getTimezone(),
            \IntlDateFormatter::GREGORIAN,
            'EEEE d MMMM y',
        );

        $formatted = $formatter->format($date);

        return false !== $formatted ? $formatted : '';
    }

    public function formatDateTime(?\DateTimeInterface $date): string
    {
        if (null === $date) {
            return '';
        }

        $formatter = new \IntlDateFormatter(
            'fr_FR',
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::SHORT,
            $date->getTimezone(),
        );

        $formatted = $formatter->format($date);

        return false !== $formatted ? $formatted : '';
    }
}
