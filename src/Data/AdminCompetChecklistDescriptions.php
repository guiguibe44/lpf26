<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Descriptions détaillées de la checklist compétition.
 */
final class AdminCompetChecklistDescriptions
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'compet-scenario-create-match' => 'EasyAdmin → Matchs : France domicile, Allemagne extérieur, date/heure 15:00, statut Programmé. Décocher « Synchro API-Football ». Laisser fixture API vide.',
            'compet-scenario-3-teams' => 'Équipe A : les 2 joueurs ont un prono. Équipe B : J1 prono, J2 sans ligne Pronostic. Équipe C : aucun prono. Tous cotisationPayee = true.',
            'compet-scenario-buteurs-picked' => 'Chaque joueur a un buteur. Pour les buts : 2 buteurs FR choisis par au moins une équipe, 1 buteur DE que personne n’a choisi.',
            'compet-scenario-ids-recorded' => 'Saisir dans le panneau jaune en haut de page : MATCH_ID, BUTEUR_FR_1, BUTEUR_DE_1, BUTEUR_FR_2. Les URLs cron et commandes se mettent à jour.',
            'compet-scenario-crons-planned' => 'Sur cron-job.org : 6 tâches ponctuelles (14h, 15h, 15:23, 15:45, 16:07, 17:05) ou lancer les commandes à la main. Voir tableau « Planning ».',
            'compet-scenario-jokers-before' => 'Si vous testez les jokers : les placer avant 15:00 depuis le drawer de la carte match.',

            'compet-scenario-reset-reminder' => 'php bin/console app:test-match:step --match-id=MATCH_ID --step=reset-reminder — uniquement pour rejouer la relance de 14h.',
            'compet-scenario-14h-reminder' => 'php bin/console app:test-match:step --match-id=MATCH_ID --step=reminder — ou cron test-match-step. Alternative : cron pronostic-reminders toutes les 5 min vers 14h.',
            'compet-scenario-15h-kickoff' => 'step kickoff : statut LIVE, 0-0, ensureDefaultsForMatch pour B-J2 et C-J1/J2.',
            'compet-scenario-23-goal-fr1' => 'step goal --buteur-id=BUTEUR_FR_1 --minute=23. Vérifier score 1-0 et notif push buteur.',
            'compet-scenario-45-goal-de' => 'step goal --buteur-id=BUTEUR_DE_1 --minute=45. Score 1-1. Aucun point buteur si DE non sélectionné.',
            'compet-scenario-67-goal-fr2' => 'step goal --buteur-id=BUTEUR_FR_2 --minute=67. Score 2-1.',
            'compet-scenario-17h-finish' => 'step finish : finalizeMatchAfterFullTime, rebuildSnapshotsFromMatch, rescore buteurs.',

            'compet-scenario-verify-reminder-count' => 'Admin → Historique relances : 3 envois (ou 3 lignes) pour les joueurs sans prono.',
            'compet-scenario-verify-prono-matrix' => 'Comparer les points en admin Pronostics avec le tableau « Résultats pronos attendus » du panneau scénario.',
            'compet-scenario-verify-buteur-points' => 'Joueurs avec buteur FR ayant marqué : points buteur > 0. Personne ne gagne sur le but DE si Musiala (ex.) non choisi.',
            'compet-scenario-verify-ranking' => 'Classement général : ordre cohérent, équipe A en tête si seul score exact.',
            'compet-scenario-verify-finalized' => 'Fiche match : statut Terminé, scores 2-1, liveScoresFinalizedAt non null.',

            'compet-prono-lock-kickoff' => 'Après kickoff ou statut LIVE : formulaire score grisé sur /matchs.',
            'compet-prono-partner-visible' => 'Coéquipier : prono affiché sur la carte avant et pendant le match.',
            'compet-prono-finished-detail' => 'Lien vers la page détail des pronos du match.',

            'compet-joker-effects' => 'Recalcul après finish si jokers offensifs/défensifs placés.',

            'compet-points-exact' => 'Défaut 3 pts × cote pour score exact (ex. A-J1 avec 2-1).',
            'compet-points-good-result' => 'Défaut 1 pt pour bon vainqueur/nul sans score exact.',
            'compet-live-rescore-on-goal' => 'À chaque goal step : PronosticScoringService::rescoreForMatch.',
            'compet-rank-order' => 'Critères de tri documentés dans le règlement.',

            'compet-notif-prono-reminder' => 'Vérifier boîtes mail / push des 3 joueurs oubliés.',
            'compet-notif-buteur-goal' => 'Buts 23′ et 67′ : push « votre buteur a marqué » si préférence activée.',

            'compet-admin-cli-info' => 'Affiche kickoff, heure relance due, statut, scores. Utile avant le jour J.',
            'compet-admin-cron-endpoint' => 'GET /cron/test-match-step protégé par CRON_SECRET. Paramètres : match_id, step, buteur_id, minute.',
            'compet-admin-script-sh' => 'source scripts/test-match-scenario.env puis ./scripts/test-match-scenario.sh kickoff|goal-fr1|…',
            'compet-checklist-meta' => 'Réinitialiser les cases : bouton en haut de page. Notes : partagées en base (table admin_qa_checklist_note).',
        ];
    }
}
