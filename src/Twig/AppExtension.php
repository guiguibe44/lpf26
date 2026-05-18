<?php

namespace App\Twig;

use App\Entity\GameMatch;
use App\Service\ConnectedPlayerContext;
use App\Service\MatchStatusResolver;
use App\Service\TeamFavoriteCountryService;
use App\Service\UserNotificationContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly MatchStatusResolver $matchStatusResolver,
        private readonly TeamFavoriteCountryService $teamFavoriteCountryService,
        private readonly ConnectedPlayerContext $connectedPlayerContext,
        private readonly UserNotificationContext $userNotificationContext,
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
            new TwigFunction('match_cotes_visible', [$this, 'areMatchCotesVisible']),
            new TwigFunction('match_is_team_favorite_highlight', [$this, 'isTeamFavoriteHighlight']),
            new TwigFunction('connected_player_sidebar', [$this, 'getConnectedPlayerSidebar']),
            new TwigFunction('unread_notifications_count', [$this, 'getUnreadNotificationsCount']),
        ];
    }

    /**
     * @return array{nickname: string, avatar: string|null, initials: string, email: string}|null
     */
    public function getConnectedPlayerSidebar(): ?array
    {
        return $this->connectedPlayerContext->getSidebarProfile();
    }

    public function getUnreadNotificationsCount(): int
    {
        return $this->userNotificationContext->getUnreadCount();
    }

    /**
     * @param array{country_id?: int}|null $highlight
     */
    public function isTeamFavoriteHighlight(GameMatch $match, ?array $highlight): bool
    {
        return $this->teamFavoriteCountryService->isMatchHighlighted($highlight, $match);
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

    /** Cotes affichables à partir du coup d'envoi (live ou terminé). */
    public function areMatchCotesVisible(GameMatch $match, ?\DateTimeInterface $now = null): bool
    {
        $at = $now instanceof \DateTimeImmutable
            ? $now
            : ($now instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($now) : null);

        return $this->matchStatusResolver->isMatchStarted($match, $at);
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
