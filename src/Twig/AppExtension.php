<?php

namespace App\Twig;

use App\Entity\Buteur;
use App\Entity\Country;
use App\Entity\GameMatch;
use App\Entity\Joker;
use App\Enum\MatchCoteMode;
use App\Service\MatchCoteService;
use App\Service\MatchOutcomeResolver;
use App\Service\ConnectedPlayerContext;
use App\Service\CountryShortLabelResolver;
use App\Service\MatchLiveClockLabelResolver;
use App\Service\MatchStatusResolver;
use App\Service\TeamFavoriteCountryService;
use App\Service\TeamMatchPointsTierResolver;
use App\Service\UserNotificationContext;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly MatchStatusResolver $matchStatusResolver,
        private readonly MatchLiveClockLabelResolver $matchLiveClockLabelResolver,
        private readonly TeamFavoriteCountryService $teamFavoriteCountryService,
        private readonly CountryShortLabelResolver $countryShortLabelResolver,
        private readonly ConnectedPlayerContext $connectedPlayerContext,
        private readonly UserNotificationContext $userNotificationContext,
        private readonly TeamMatchPointsTierResolver $teamMatchPointsTierResolver,
        private readonly MatchCoteService $matchCoteService,
        private readonly MatchOutcomeResolver $matchOutcomeResolver,
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
            new TwigFunction('match_live_clock_label', [$this, 'getMatchLiveClockLabel']),
            new TwigFunction('match_is_finished', [$this, 'isMatchFinished']),
            new TwigFunction('match_can_edit_before_kickoff', [$this, 'canEditBeforeKickoff']),
            new TwigFunction('match_cotes_visible', [$this, 'areMatchCotesVisible']),
            new TwigFunction('match_is_team_favorite_highlight', [$this, 'isTeamFavoriteHighlight']),
            new TwigFunction('country_short_label', [$this, 'getCountryShortLabel']),
            new TwigFunction('connected_player_sidebar', [$this, 'getConnectedPlayerSidebar']),
            new TwigFunction('connected_player_buteur', [$this, 'getConnectedPlayerButeur']),
            new TwigFunction('connected_team_favorite_country', [$this, 'getConnectedTeamFavoriteCountry']),
            new TwigFunction('unread_notifications_count', [$this, 'getUnreadNotificationsCount']),
            new TwigFunction('match_team_points_tier', [$this, 'getMatchTeamPointsTier']),
            new TwigFunction('match_team_points_tier_label', [$this, 'getMatchTeamPointsTierLabel']),
            new TwigFunction('joker_tabler_icon', [$this, 'getJokerTablerIcon']),
            new TwigFunction('match_cote_mode', [$this, 'getMatchCoteMode']),
            new TwigFunction('match_cote_is_one_n_two', [$this, 'isMatchCoteOneNTwo']),
            new TwigFunction('match_outcome_from_scores', [$this, 'getMatchOutcomeFromScores']),
        ];
    }

    /**
     * Issue 1/N/2 (HOME, DRAW, AWAY) à partir du score réel, ou null si incomplet.
     */
    public function getMatchOutcomeFromScores(?int $scoreHome, ?int $scoreAway): ?string
    {
        if (null === $scoreHome || null === $scoreAway) {
            return null;
        }

        return $this->matchOutcomeResolver->resolve($scoreHome, $scoreAway);
    }

    public function getMatchCoteMode(): string
    {
        return $this->matchCoteService->getActiveMode()->value;
    }

    public function isMatchCoteOneNTwo(): bool
    {
        return MatchCoteMode::ONE_N_TWO === $this->matchCoteService->getActiveMode();
    }

    public function getJokerTablerIcon(?string $code): string
    {
        return Joker::tablerIconClassForCode($code);
    }

    public function getMatchTeamPointsTier(int $points): string
    {
        return $this->teamMatchPointsTierResolver->resolveTier($points);
    }

    public function getMatchTeamPointsTierLabel(int $points): string
    {
        return $this->teamMatchPointsTierResolver->resolveTierLabel($points);
    }

    /**
     * @return array{nickname: string, avatar: string|null, initials: string, email: string}|null
     */
    public function getConnectedPlayerSidebar(): ?array
    {
        return $this->connectedPlayerContext->getSidebarProfile();
    }

    public function getConnectedPlayerButeur(): ?Buteur
    {
        return $this->connectedPlayerContext->getButeurChoisi();
    }

    public function getConnectedTeamFavoriteCountry(): ?Country
    {
        return $this->connectedPlayerContext->getFavoriteCountry();
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

    public function getCountryShortLabel(?Country $country): string
    {
        return $this->countryShortLabelResolver->resolve($country);
    }

    public function isMatchLive(GameMatch $match, ?\DateTimeInterface $now = null): bool
    {
        $at = $now instanceof \DateTimeImmutable
            ? $now
            : ($now instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($now) : null);

        return $this->matchStatusResolver->isMatchLive($match, $at);
    }

    public function getMatchLiveClockLabel(GameMatch $match): string
    {
        return $this->matchLiveClockLabelResolver->resolve($match);
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
