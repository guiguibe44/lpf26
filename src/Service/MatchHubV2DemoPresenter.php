<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\CountryRepository;

/**
 * Données fictives pour la maquette hub match v2 (dev uniquement).
 */
final class MatchHubV2DemoPresenter
{
    public function __construct(
        private readonly CountryRepository $countryRepository,
        private readonly MatchHubV2DiscussionFeedBuilder $discussionFeedBuilder,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $state, ?User $user): array
    {
        $state = \in_array($state, ['live', 'termine', 'avenir'], true) ? $state : 'live';

        $home = $this->countryRepository->findOneBy(['nom' => 'France']);
        $away = $this->countryRepository->findOneBy(['nom' => 'Allemagne']);

        $partnerName = 'Alex';
        if ($user instanceof User) {
            $local = explode('@', $user->getEmail())[0] ?? 'Joueur';
            $partnerName = 'Partenaire_' . substr($local, 0, 3);
        }

        $isLive = 'live' === $state;
        $isFinished = 'termine' === $state;
        $isUpcoming = 'avenir' === $state;

        $scoreHome = $isUpcoming ? null : 2;
        $scoreAway = $isUpcoming ? null : ($isFinished ? 2 : 1);

        $goals = $isUpcoming ? [] : [
            ['name' => 'K. Mbappé', 'minute' => 23, 'side' => 'home', 'photo' => null],
            ['name' => 'F. Wirtz', 'minute' => 41, 'side' => 'away', 'photo' => null],
            ['name' => 'A. Griezmann', 'minute' => 58, 'side' => 'home', 'photo' => null],
        ];

        if ($isFinished) {
            $goals[] = ['name' => 'N. Füllkrug', 'minute' => 88, 'side' => 'away', 'photo' => null];
        }

        return [
            'demo_state' => $state,
            'is_live' => $isLive,
            'is_finished' => $isFinished,
            'is_upcoming' => $isUpcoming,
            'home_country' => $home,
            'away_country' => $away,
            'home_label' => $home?->getNom() ?? 'France',
            'away_label' => $away?->getNom() ?? 'Allemagne',
            'match_date' => new \DateTimeImmutable('2026-06-15 21:00:00'),
            'match_phase' => 'Groupe A — Match test',
            'match_venue' => 'Stade de démo, Paris',
            'score_home' => $scoreHome,
            'score_away' => $scoreAway,
            'clock_label' => $isLive ? '67\' — 2e mi-temps' : null,
            'viewer_pronostic' => [
                'pred_home' => 2,
                'pred_away' => 1,
                'points' => $isUpcoming ? 0 : ($isLive ? 8 : 12),
                'score_inverted' => false,
                'prise_risque' => false,
            ],
            'partner_pronostics' => [
                [
                    'name' => $partnerName,
                    'initial' => strtoupper(substr($partnerName, 0, 1)),
                    'score_home' => 1,
                    'score_away' => 1,
                ],
            ],
            'match_joker' => [
                'name' => 'Miroir',
                'code' => 'miroir',
            ],
            'team_match_points' => $isUpcoming ? null : ($isLive ? 14 : 18),
            'match_goals' => $goals,
            'ranking_rows' => $this->buildRankingRows($isUpcoming),
            'match_jokers' => $isUpcoming ? [] : [
                [
                    'code' => 'miroir',
                    'name' => 'Miroir',
                    'icon' => 'ti-arrows-exchange',
                    'image' => null,
                    'neutralized' => false,
                    'stories' => [
                        'Les Bleus du Désert ont activé le Miroir : un score inversé peut changer la donne.',
                    ],
                ],
            ],
            'discussion_feed' => $this->discussionFeedBuilder->buildDemoFeed(
                $state,
                $home?->getNom() ?? 'France',
                $away?->getNom() ?? 'Allemagne',
                $partnerName,
            ),
            'state_links' => [
                ['etat' => 'live', 'label' => 'En direct'],
                ['etat' => 'termine', 'label' => 'Terminé'],
                ['etat' => 'avenir', 'label' => 'À venir'],
            ],
        ];
    }

    /**
     * @return list<array{
     *     position: int,
     *     team_name: string,
     *     total: int,
     *     match_pts: int,
     *     prono1: string,
     *     prono2: string,
     *     is_viewer: bool
     * }>
     */
    private function buildRankingRows(bool $isUpcoming): array
    {
        if ($isUpcoming) {
            return [];
        }

        return [
            [
                'position' => 1,
                'team_name' => 'Les Bleus du Désert',
                'total' => 142,
                'match_pts' => 18,
                'prono1' => '2-1',
                'prono2' => '1-1',
                'is_viewer' => true,
            ],
            [
                'position' => 2,
                'team_name' => 'Mannschaft FC',
                'total' => 138,
                'match_pts' => 11,
                'prono1' => '1-2',
                'prono2' => '0-2',
                'is_viewer' => false,
            ],
            [
                'position' => 3,
                'team_name' => 'Tiki-Taka Bros',
                'total' => 131,
                'match_pts' => 9,
                'prono1' => '2-0',
                'prono2' => '2-0',
                'is_viewer' => false,
            ],
            [
                'position' => 4,
                'team_name' => 'Underdogs United',
                'total' => 120,
                'match_pts' => 6,
                'prono1' => '1-1',
                'prono2' => '1-0',
                'is_viewer' => false,
            ],
        ];
    }
}
