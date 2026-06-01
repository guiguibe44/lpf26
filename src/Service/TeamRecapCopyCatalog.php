<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Catalogue admin des textes du récap d’équipe (tous les cas).
 */
final class TeamRecapCopyCatalog
{
    public function __construct(
        private readonly TeamRecapFunCopy $funCopy,
        private readonly TeamRecapMailer $mailer,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAdminViewModel(): array
    {
        return [
            'schedule' => [
                'interval' => sprintf('Tous les %d jours', BiDailyRecapSchedule::INTERVAL_DAYS),
                'send_time' => BiDailyRecapSchedule::SEND_TIME.' ('.BiDailyRecapSchedule::TIMEZONE.')',
                'cron_url' => '/cron/team-recap?token=…',
                'command' => 'php bin/console app:email:team-recap',
            ],
            'edit_url' => 'admin_team_recap_copy_index',
            'intro_pools' => $this->funCopy->catalogIntroPools(),
            'intro_extras' => $this->funCopy->catalogIntroExtras(),
            'intro_pick_note' => $this->funCopy->pickIntroLineNote(),
            'laggard_variants' => $this->funCopy->catalogLaggardVariants(),
            'champion_teases' => $this->funCopy->catalogChampionTeases(),
            'ranking_cheers' => $this->funCopy->catalogRankingCheers(),
            'subjects' => $this->catalogSubjects(),
            'static_blocks' => $this->catalogStaticBlocks(),
            'placeholders' => $this->placeholderHelp(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSampleRecapContext(): array
    {
        $laggard = $this->funCopy->buildLaggardCopy('Pilou', 12);

        return [
            'team_id' => 0,
            'team_name' => 'Les Renards',
            'period_label' => '14 au 16 juin 2026',
            'intro_line' => $this->funCopy->buildIntro('Les Renards', '14 au 16 juin 2026', 87, [
                ['nickname' => 'Zaza', 'points' => 58],
                ['nickname' => 'Pilou', 'points' => 12],
            ]),
            'total_team_points' => 87,
            'matches_count' => 3,
            'laggard' => array_merge($laggard, [
                'nickname' => 'Pilou',
                'points' => 12,
                'exact_scores' => 0,
                'good_results' => 1,
            ]),
            'champion_tease' => $this->funCopy->buildChampionTease('Zaza', 58, 'Pilou', 46),
            'matches' => [
                [
                    'label' => 'France — Allemagne',
                    'score' => '2-1',
                    'date' => '15/06 18:00',
                    'team_points' => 40,
                    'players' => [
                        ['nickname' => 'Pilou', 'prono' => '1-1', 'points' => 10, 'outcome' => 'bon 1/N/2', 'bigballs' => false],
                        ['nickname' => 'Zaza', 'prono' => '2-1', 'points' => 30, 'outcome' => 'score exact', 'bigballs' => true],
                    ],
                    'bigballs' => ['attempted' => true, 'succeeded' => true],
                ],
            ],
            'bigballs_summary' => ['attempted' => 1, 'succeeded' => 1],
            'goals' => [
                ['nickname' => 'Zaza', 'buteur' => 'Mbappé', 'match' => 'France — Allemagne', 'minute' => 23, 'points' => 33],
            ],
            'ranking' => [
                'before' => ['position' => 12, 'total' => 312, 'teams_count' => 48],
                'after' => ['position' => 9, 'total' => 399, 'teams_count' => 48],
                'delta_positions' => 3,
                'delta_points' => 87,
            ],
            'ranking_cheer' => $this->funCopy->buildRankingCheer(3, 87),
            'jokers_placed' => [
                ['name' => 'Double équipe', 'match' => 'France — Allemagne'],
            ],
            'jokers_suffered' => [
                ['name' => 'Pique de points', 'match' => 'France — Allemagne', 'blocked' => true],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function placeholderHelp(): array
    {
        return [
            '{nickname} — surnom du joueur mis en avant',
            '{points} — points du joueur sur la période',
            '{team_name}, {total_points}, {period_label}',
            '{best_nickname}, {worst_nickname}, {best_points}, {gap}',
            '{delta_positions}, {delta_points}, {delta_positions_abs}',
            '{laggard_nickname} — objet e-mail',
        ];
    }

    /**
     * @return list<array{condition: string, example: string}>
     */
    private function catalogSubjects(): array
    {
        $team = 'Les Renards';
        $period = '14 au 16 juin 2026';

        return [
            [
                'condition' => '≥ 50 pts équipe (code subject.hot)',
                'example' => $this->mailer->buildSubject([
                    'team_name' => $team,
                    'total_team_points' => 87,
                    'laggard' => ['nickname' => 'Pilou'],
                    'period_label' => $period,
                ]),
            ],
            [
                'condition' => 'Points > 0 (subject.positive)',
                'example' => $this->mailer->buildSubject([
                    'team_name' => $team,
                    'total_team_points' => 24,
                    'laggard' => ['nickname' => 'Pilou'],
                ]),
            ],
            [
                'condition' => '0 pt (subject.neutral)',
                'example' => $this->mailer->buildSubject([
                    'team_name' => $team,
                    'total_team_points' => 0,
                    'period_label' => $period,
                    'laggard' => ['nickname' => 'Pilou'],
                ]),
            ],
        ];
    }

    /**
     * @return list<array{title: string, texts: list<string>}>
     */
    private function catalogStaticBlocks(): array
    {
        return [
            [
                'title' => 'Mise en avant (encadré orange)',
                'texts' => [
                    'Joueur avec le moins de points sur la période (si duo et deux joueurs distincts).',
                    'Titre + blurb + total pts du joueur.',
                    'Rappel du meilleur coéquipier en dessous (champion.tease.*).',
                ],
            ],
            [
                'title' => 'Pied de page',
                'texts' => [
                    'CTA fiche équipe · lien classement',
                    'Désactivation : Compte → Notifications → Récap d’équipe',
                ],
            ],
        ];
    }
}
