<?php

namespace App\Twig;

use App\Entity\GameMatch;
use App\Service\MatchStatusResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly MatchStatusResolver $matchStatusResolver,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('fr_date_long', [$this, 'formatDateLong']),
            new TwigFilter('fr_datetime', [$this, 'formatDateTime']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('match_is_live', [$this, 'isMatchLive']),
            new TwigFunction('match_is_finished', [$this, 'isMatchFinished']),
            new TwigFunction('match_can_edit_before_kickoff', [$this, 'canEditBeforeKickoff']),
        ];
    }

    public function isMatchLive(GameMatch $match, ?\DateTimeInterface $now = null): bool
    {
        $at = $now instanceof \DateTimeImmutable
            ? $now
            : ($now instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($now) : null);

        return $this->matchStatusResolver->isMatchLive($match, $at);
    }

    public function isMatchFinished(GameMatch $match, ?\DateTimeInterface $now = null): bool
    {
        $at = $now instanceof \DateTimeImmutable
            ? $now
            : ($now instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($now) : null);

        return $this->matchStatusResolver->isMatchFinished($match, $at);
    }

    public function canEditBeforeKickoff(GameMatch $match, ?\DateTimeInterface $now = null): bool
    {
        $at = $now instanceof \DateTimeImmutable
            ? $now
            : ($now instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($now) : null);

        return $this->matchStatusResolver->canEditBeforeKickoff($match, $at);
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
