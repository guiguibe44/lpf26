<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Données du scénario match test France – Allemagne (15h, sans API).
 */
final class AdminCompetChecklistScenario
{
    public const string TITLE = 'France – Allemagne · 15h00 · sans API';

    public const string TIMEZONE = 'Europe/Paris';

    /**
     * @return array{
     *     title: string,
     *     subtitle: string,
     *     kickoff_label: string,
     *     final_score: string,
     *     match_fields: list<array{label: string, value: string}>,
     *     teams_matrix: list<array{team: string, j1: string, j2: string, prono: string, buteur: string}>,
     *     buteurs_roles: list<array{role: string, example: string, var: string}>,
     *     timeline: list<array{time: string, step: string, label: string, effect: string, cli: string, cron_params: string}>,
     *     prono_results: list<array{team: string, prono: string, expected: string}>,
     *     verifications: list<array{step: string, checks: string}>,
     *     troubleshooting: list<array{problem: string, cause: string}>,
     *     cron_path: string,
     *     cli_command: string,
     *     script_path: string,
     *     reminder_note: string,
     * }
     */
    public static function playbook(string $cronPath = '/cron/test-match-step'): array
    {
        return [
            'title' => self::TITLE,
            'subtitle' => 'Match manuel · coup d’envoi 15:00 (Paris) · score final 2-1',
            'kickoff_label' => '15:00',
            'final_score' => '2-1',
            'match_fields' => [
                ['label' => 'Pays domicile', 'value' => 'France'],
                ['label' => 'Pays extérieur', 'value' => 'Allemagne'],
                ['label' => 'Date / heure', 'value' => 'Jour J à 15:00'],
                ['label' => 'Statut initial', 'value' => 'Programmé (SCHEDULED)'],
                ['label' => 'Synchro API-Football', 'value' => 'Non'],
                ['label' => 'Fixture API', 'value' => 'vide'],
            ],
            'teams_matrix' => [
                [
                    'team' => 'A',
                    'j1' => 'Prono saisi (ex. 2-1)',
                    'j2' => 'Prono saisi (ex. 1-1)',
                    'prono' => 'Les deux ont une ligne Pronostic',
                    'buteur' => 'FR + DE (au moins un par pays)',
                ],
                [
                    'team' => 'B',
                    'j1' => 'Prono saisi (ex. 1-0)',
                    'j2' => 'Aucune ligne Pronostic',
                    'prono' => 'Joueur 2 → relance à 14h',
                    'buteur' => 'Au moins un buteur FR',
                ],
                [
                    'team' => 'C',
                    'j1' => 'Aucune ligne Pronostic',
                    'j2' => 'Aucune ligne Pronostic',
                    'prono' => 'Double oubli → 2 relances',
                    'buteur' => 'Mix FR / DE',
                ],
            ],
            'buteurs_roles' => [
                ['role' => '1er but France (sélectionné)', 'example' => 'ex. Mbappé', 'var' => 'BUTEUR_FR_1'],
                ['role' => 'But Allemagne (non sélectionné par personne)', 'example' => 'ex. Musiala', 'var' => 'BUTEUR_DE_1'],
                ['role' => '2e but France (sélectionné)', 'example' => 'ex. Griezmann', 'var' => 'BUTEUR_FR_2'],
            ],
            'timeline' => [
                [
                    'time' => '14:00',
                    'step' => 'reminder',
                    'label' => 'Relance pronos oubliés',
                    'effect' => 'E-mail / push vers 3 joueurs (B-J2, C-J1, C-J2)',
                    'cli' => 'app:test-match:step --match-id={MATCH_ID} --step=reminder',
                    'cron_params' => 'step=reminder',
                ],
                [
                    'time' => '15:00',
                    'step' => 'kickoff',
                    'label' => 'Coup d’envoi',
                    'effect' => 'LIVE 0-0 · pronos 0-0 auto pour les oublis',
                    'cli' => 'app:test-match:step --match-id={MATCH_ID} --step=kickoff',
                    'cron_params' => 'step=kickoff',
                ],
                [
                    'time' => '15:23',
                    'step' => 'goal',
                    'label' => '1er but France (23′)',
                    'effect' => 'Score 1-0 · points buteur · notif push',
                    'cli' => 'app:test-match:step --match-id={MATCH_ID} --step=goal --buteur-id={BUTEUR_FR_1} --minute=23',
                    'cron_params' => 'step=goal&buteur_id={BUTEUR_FR_1}&minute=23',
                ],
                [
                    'time' => '15:45',
                    'step' => 'goal',
                    'label' => 'But Allemagne (45′)',
                    'effect' => 'Score 1-1 · pas de points buteur si non choisi',
                    'cli' => 'app:test-match:step --match-id={MATCH_ID} --step=goal --buteur-id={BUTEUR_DE_1} --minute=45',
                    'cron_params' => 'step=goal&buteur_id={BUTEUR_DE_1}&minute=45',
                ],
                [
                    'time' => '16:07',
                    'step' => 'goal',
                    'label' => '2e but France (67′)',
                    'effect' => 'Score 2-1',
                    'cli' => 'app:test-match:step --match-id={MATCH_ID} --step=goal --buteur-id={BUTEUR_FR_2} --minute=67',
                    'cron_params' => 'step=goal&buteur_id={BUTEUR_FR_2}&minute=67',
                ],
                [
                    'time' => '17:05',
                    'step' => 'finish',
                    'label' => 'Fin du match',
                    'effect' => 'FINISHED · finalisation · classement recalculé',
                    'cli' => 'app:test-match:step --match-id={MATCH_ID} --step=finish',
                    'cron_params' => 'step=finish',
                ],
            ],
            'prono_results' => [
                ['team' => 'A – J1', 'prono' => '2-1', 'expected' => 'Score exact'],
                ['team' => 'A – J2', 'prono' => '1-1', 'expected' => 'Bon 1N2 (nul)'],
                ['team' => 'B – J1', 'prono' => '1-0', 'expected' => 'Bon 1N2 (victoire FR)'],
                ['team' => 'B – J2', 'prono' => '0-0 (auto)', 'expected' => 'Mauvais'],
                ['team' => 'C – J1 / J2', 'prono' => '0-0 (auto)', 'expected' => 'Mauvais'],
            ],
            'verifications' => [
                ['step' => 'reminder', 'checks' => 'Historique relances prono · 3 joueurs ciblés'],
                ['step' => 'kickoff', 'checks' => '/matchs : en direct · oublis passés en 0-0'],
                ['step' => 'goal', 'checks' => 'Admin Buts · score carte · points prono recalculés'],
                ['step' => 'finish', 'checks' => 'liveScoresFinalizedAt · page Classement'],
            ],
            'troubleshooting' => [
                ['problem' => 'Relance non envoyée', 'cause' => 'Ligne Pronostic déjà existante, ou pushReminderSentAt déjà set → step reset-reminder'],
                ['problem' => 'Relance trop tôt', 'cause' => 'Due seulement à partir de 14h pour un CO à 15h'],
                ['problem' => 'Erreur synchro API', 'cause' => 'Désactiver « Synchro API-Football » sur le match'],
                ['problem' => 'But refusé', 'cause' => 'Pays du buteur ≠ France / Allemagne du match'],
            ],
            'cron_path' => $cronPath,
            'cli_command' => 'php bin/console',
            'script_path' => 'scripts/test-match-scenario.sh',
            'reminder_note' => 'Seuls les joueurs cotisés sans aucune ligne Pronostic sur ce match sont relancés. Le cron /cron/pronostic-reminders (5 min) peut aussi déclencher la relance vers 14h.',
        ];
    }
}
