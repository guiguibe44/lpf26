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
                    'j1' => '2-1 France',
                    'j2' => '1-1',
                    'prono' => 'Les deux remplissent avant 14h',
                    'buteur' => 'Dembélé (FR) + Havertz (DE)',
                ],
                [
                    'team' => 'B',
                    'j1' => 'Rien avant 14h → relance ; puis 1-2 (victoire DE) avant 15h',
                    'j2' => 'Aucune ligne Pronostic jusqu’au coup d’envoi',
                    'prono' => 'Relance B-J1 et B-J2 à 14h ; seul J1 corrige',
                    'buteur' => 'Doué (FR) + un buteur hors France/Allemagne',
                ],
                [
                    'team' => 'C',
                    'j1' => '2-1 France',
                    'j2' => 'Aucune ligne Pronostic',
                    'prono' => 'J2 relancé à 14h ; 0-0 auto au coup d’envoi',
                    'buteur' => 'Au choix (ex. mix FR/DE)',
                ],
            ],
            'buteurs_roles' => [
                ['role' => '1er but France (sélectionné)', 'example' => 'ex. Dembélé → BUTEUR_FR_1', 'var' => 'BUTEUR_FR_1'],
                ['role' => 'But Allemagne (ex. Havertz sur grille A)', 'example' => 'ex. Havertz → BUTEUR_DE_1', 'var' => 'BUTEUR_DE_1'],
                ['role' => '2e but France (ex. Doué sur grille B)', 'example' => 'ex. Doué → BUTEUR_FR_2', 'var' => 'BUTEUR_FR_2'],
            ],
            'timeline' => [
                [
                    'time' => '14:00',
                    'step' => 'reminder',
                    'label' => 'Relance pronos oubliés',
                    'effect' => 'E-mail / push vers 3 joueurs sans ligne : B-J1, B-J2, C-J2',
                    'cli' => 'app:test-match:step --match-id={MATCH_ID} --step=reminder',
                    'cron_params' => 'step=reminder',
                ],
                [
                    'time' => '15:00',
                    'step' => 'kickoff',
                    'label' => 'Coup d’envoi',
                    'effect' => 'LIVE 0-0 · 0-0 auto pour B-J2 et C-J2 (sans ligne à 15h)',
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
                ['team' => 'A – J1', 'prono' => '2-1', 'expected' => 'Score exact (30 pts × cote, sans joker)'],
                ['team' => 'A – J2', 'prono' => '1-1', 'expected' => 'Mauvais (nul prédit, victoire France réelle)'],
                ['team' => 'B – J1', 'prono' => '1-2', 'expected' => 'Mauvais (victoire extérieure prédite)'],
                ['team' => 'B – J2', 'prono' => '0-0 (auto)', 'expected' => 'Mauvais'],
                ['team' => 'C – J1', 'prono' => '2-1', 'expected' => 'Score exact'],
                ['team' => 'C – J2', 'prono' => '0-0 (auto)', 'expected' => 'Mauvais'],
            ],
            'verifications' => [
                ['step' => 'reminder', 'checks' => 'Historique relances prono · B-J1, B-J2, C-J2'],
                ['step' => 'kickoff', 'checks' => '/matchs : en direct · 0-0 auto pour B-J2 et C-J2 seulement'],
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
            'reminder_note' => 'Seuls les joueurs cotisés sans aucune ligne Pronostic sur ce match sont relancés (configuration type : B-J1, B-J2, C-J2). Après l’e-mail, B-J1 saisit 1-2 avant 15h. Le cron /cron/pronostic-reminders (5 min) peut aussi déclencher la relance vers 14h.',
        ];
    }
}
