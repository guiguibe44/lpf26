<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Scénario de recette jokers : 3 équipes (A, B, C), 3 matchs le même jour (demain matin).
 */
final class JokerTestScenarioDefinition
{
    /** Marqueur stocké dans {@see GameMatch::venueName} pour retrouver les matchs du scénario. */
    public const MATCH_MARKER = 'TEST_JOKERS';

    public const TIMEZONE = 'Europe/Paris';

    public const DEFAULT_PASSWORD = 'Test1234!';

    /** @var array<string, array{name: string, slogan: string, players: list<array{email: string, nickname: string, buteur_country: string}>}> */
    public const TEAMS = [
        'A' => [
            'name' => 'Équipe A',
            'slogan' => 'Scénario jokers — A',
            'players' => [
                ['email' => 'joker-a1@lpf26.local', 'nickname' => 'joker_a1', 'buteur_country' => 'France'],
                ['email' => 'joker-a2@lpf26.local', 'nickname' => 'joker_a2', 'buteur_country' => 'Germany'],
            ],
        ],
        'B' => [
            'name' => 'Équipe B',
            'slogan' => 'Scénario jokers — B',
            'favorite_country' => 'France',
            'players' => [
                ['email' => 'joker-b1@lpf26.local', 'nickname' => 'joker_b1', 'buteur_country' => 'France'],
                ['email' => 'joker-b2@lpf26.local', 'nickname' => 'joker_b2', 'buteur_country' => 'Spain'],
            ],
        ],
        'C' => [
            'name' => 'Équipe C',
            'slogan' => 'Scénario jokers — C',
            'players' => [
                ['email' => 'joker-c1@lpf26.local', 'nickname' => 'joker_c1', 'buteur_country' => 'Germany'],
                ['email' => 'joker-c2@lpf26.local', 'nickname' => 'joker_c2', 'buteur_country' => 'Spain'],
            ],
        ],
    ];

    /**
     * @var list<array{home: string, away: string, kickoff_hour: int, kickoff_minute: int, group_phase: string}>
     */
    public const MATCHES = [
        ['home' => 'France', 'away' => 'Germany', 'kickoff_hour' => 9, 'kickoff_minute' => 0, 'group_phase' => 'Group B'],
        ['home' => 'France', 'away' => 'Spain', 'kickoff_hour' => 11, 'kickoff_minute' => 0, 'group_phase' => 'Group B'],
        ['home' => 'Germany', 'away' => 'Spain', 'kickoff_hour' => 13, 'kickoff_minute' => 0, 'group_phase' => 'Group B'],
    ];

    /**
     * Pronos par équipe [domicile, extérieur] pour chaque match (index 0..2).
     *
     * @var array<string, list<array{int, int}>>
     */
    public const PRONOSTICS_BY_TEAM = [
        'A' => [[1, 0], [2, 1], [0, 1]],
        'B' => [[0, 0], [1, 0], [1, 0]],
        'C' => [[1, 1], [0, 2], [2, 0]],
    ];

    /**
     * Jokers posés avant coup d'envoi : match_index, team_key, joker_code, target_team_key|null.
     *
     * @var list<array{match_index: int, team: string, joker: string, target: ?string}>
     */
    public const JOKER_PLACEMENTS = [
        ['match_index' => 0, 'team' => 'A', 'joker' => 'double_equipe', 'target' => null],
        ['match_index' => 0, 'team' => 'B', 'joker' => 'espion', 'target' => null],
        ['match_index' => 0, 'team' => 'C', 'joker' => 'double_buteur', 'target' => null],
        ['match_index' => 1, 'team' => 'B', 'joker' => 'bouclier', 'target' => null],
        ['match_index' => 1, 'team' => 'C', 'joker' => 'pique_points', 'target' => 'B'],
        ['match_index' => 1, 'team' => 'A', 'joker' => 'collecte_points', 'target' => null],
        ['match_index' => 2, 'team' => 'A', 'joker' => 'inverse_score', 'target' => 'B'],
        ['match_index' => 2, 'team' => 'C', 'joker' => 'inverse_buteur', 'target' => 'A'],
    ];

    /**
     * Scores finaux attendus [domicile, extérieur] par match.
     *
     * @var list<array{int, int}>
     */
    public const FINAL_SCORES = [
        [1, 0],
        [2, 1],
        [0, 1],
    ];

    /**
     * Buteurs marqueurs (pays du buteur) par étape goal.
     *
     * @var array<string, string>
     */
    public const GOAL_SCORER_COUNTRY = [
        'm1_goal' => 'France',
        'm2_goal_home' => 'France',
        'm2_goal_away' => 'Spain',
        'm3_goal' => 'Spain',
    ];

    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     description: string,
     *     type: string,
     *     match_index?: int,
     *     score?: array{int, int},
     *     goal_key?: string
     * }>
     */
    public static function steps(): array
    {
        return [
            [
                'id' => 'ready',
                'label' => 'Prêt',
                'description' => 'Jokers posés, pronos saisis. Vérifiez le drawer joker sur chaque match.',
                'type' => 'info',
            ],
            [
                'id' => 'm1_kickoff',
                'label' => 'M1 — Coup d\'envoi',
                'description' => 'France – Germany : passage en LIVE, pronos 0-0 par défaut si manquants.',
                'type' => 'kickoff',
                'match_index' => 0,
            ],
            [
                'id' => 'm1_goal',
                'label' => 'M1 — But (France)',
                'description' => 'Score 1-0. Double équipe (A) et double buteur (C) recalculés.',
                'type' => 'goal',
                'match_index' => 0,
                'goal_key' => 'm1_goal',
            ],
            [
                'id' => 'm1_finish',
                'label' => 'M1 — Fin',
                'description' => 'Fin du match 1-0, classement et points figés pour M1.',
                'type' => 'finish',
                'match_index' => 0,
                'score' => self::FINAL_SCORES[0],
            ],
            [
                'id' => 'm2_kickoff',
                'label' => 'M2 — Coup d\'envoi',
                'description' => 'France – Spain. Bouclier (B) actif toute la journée.',
                'type' => 'kickoff',
                'match_index' => 1,
            ],
            [
                'id' => 'm2_goal_home',
                'label' => 'M2 — But France',
                'description' => 'Premier but domicile.',
                'type' => 'goal',
                'match_index' => 1,
                'goal_key' => 'm2_goal_home',
            ],
            [
                'id' => 'm2_goal_away',
                'label' => 'M2 — But Spain',
                'description' => 'Deuxième but extérieur.',
                'type' => 'goal',
                'match_index' => 1,
                'goal_key' => 'm2_goal_away',
            ],
            [
                'id' => 'm2_finish',
                'label' => 'M2 — Fin',
                'description' => 'Fin 2-1. Pique vers B neutralisée ; collecte (A) appliquée.',
                'type' => 'finish',
                'match_index' => 1,
                'score' => self::FINAL_SCORES[1],
            ],
            [
                'id' => 'm3_kickoff',
                'label' => 'M3 — Coup d\'envoi',
                'description' => 'Germany – Spain.',
                'type' => 'kickoff',
                'match_index' => 2,
            ],
            [
                'id' => 'm3_goal',
                'label' => 'M3 — But Spain',
                'description' => 'But extérieur, score 0-1.',
                'type' => 'goal',
                'match_index' => 2,
                'goal_key' => 'm3_goal',
            ],
            [
                'id' => 'm3_finish',
                'label' => 'M3 — Fin',
                'description' => 'Fin 0-1. Inverse score (A→B) et inverse buteur (C→A).',
                'type' => 'finish',
                'match_index' => 2,
                'score' => self::FINAL_SCORES[2],
            ],
            [
                'id' => 'done',
                'label' => 'Terminé',
                'description' => 'Scénario jokers terminé. Consultez les tableaux de points ci-dessous.',
                'type' => 'info',
            ],
        ];
    }
}
