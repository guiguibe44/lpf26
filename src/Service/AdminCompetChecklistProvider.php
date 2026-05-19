<?php

declare(strict_types=1);

namespace App\Service;

use App\Data\AdminCompetChecklistDescriptions;

/**
 * Checklist de recette « compétition » : scénario France–Allemagne 15h + vérifications cycle match.
 *
 * @phpstan-import-type QaItem from AdminQaChecklistProvider
 * @phpstan-import-type QaSection from AdminQaChecklistProvider
 */
final class AdminCompetChecklistProvider
{
    /**
     * @return list<QaSection>
     */
    public function getSections(): array
    {
        $sections = [
            $this->sectionScenarioPreparation(),
            $this->sectionScenarioTimeline(),
            $this->sectionScenarioVerification(),
            $this->sectionPronostiques(),
            $this->sectionJokers(),
            $this->sectionPointsEtClassement(),
            $this->sectionNotifications(),
            $this->sectionControlesAdmin(),
        ];

        return $this->enrichWithDescriptions($sections);
    }

    /**
     * @param list<QaSection> $sections
     *
     * @return list<QaSection>
     */
    private function enrichWithDescriptions(array $sections): array
    {
        $descriptions = AdminCompetChecklistDescriptions::all();

        foreach ($sections as &$section) {
            foreach ($section['items'] as &$item) {
                $item['description'] = $descriptions[$item['id']] ?? '';
            }
            unset($item);
        }
        unset($section);

        return $sections;
    }

    /**
     * @return list<string>
     */
    public function getAllItemIds(): array
    {
        $ids = [];
        foreach ($this->getSections() as $section) {
            foreach ($section['items'] as $item) {
                $ids[] = $item['id'];
            }
        }

        return $ids;
    }

    /**
     * @return QaSection
     */
    private function sectionScenarioPreparation(): array
    {
        return [
            'id' => 'scenario-prep',
            'title' => 'Scénario FR–DE — Préparation',
            'icon' => 'ti-tool',
            'description' => 'Avant 14h : match, 3 équipes, buteurs, IDs notés dans le panneau ci-dessus.',
            'items' => [
                [
                    'id' => 'compet-scenario-create-match',
                    'label' => 'Créer le match France (dom.) – Allemagne (ext.) à 15:00, statut Programmé, synchro API désactivée.',
                    'kind' => 'action',
                    'route' => 'admin_game_match_index',
                    'route_label' => 'Matchs (admin)',
                ],
                [
                    'id' => 'compet-scenario-3-teams',
                    'label' => '3 équipes × 2 joueurs cotisés : pronos variés + oublis (voir matrice équipes).',
                    'kind' => 'action',
                    'route' => 'app_matches',
                    'route_label' => 'Page Matchs',
                ],
                [
                    'id' => 'compet-scenario-buteurs-picked',
                    'label' => 'Buteurs choisis (FR/DE) + 3 buteurs pour les buts simulés (IDs dans le panneau).',
                    'kind' => 'action',
                ],
                [
                    'id' => 'compet-scenario-ids-recorded',
                    'label' => 'Renseigner MATCH_ID et BUTEUR_* dans le panneau « IDs du scénario » (sauvegarde locale).',
                    'kind' => 'action',
                ],
                [
                    'id' => 'compet-scenario-crons-planned',
                    'label' => 'Planifier les crons (cron-job.org) ou préparer les commandes CLI pour chaque heure.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'compet-scenario-jokers-before',
                    'label' => 'Optionnel : placer les jokers sur le match avant 15:00.',
                    'kind' => 'attention',
                    'route' => 'app_jokers',
                    'route_label' => 'Jokers',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionScenarioTimeline(): array
    {
        return [
            'id' => 'scenario-timeline',
            'title' => 'Scénario FR–DE — Déroulé horaire',
            'icon' => 'ti-clock-play',
            'description' => 'Exécuter chaque étape à l’heure indiquée (CLI, script ou cron HTTP). Cocher après vérification.',
            'items' => [
                [
                    'id' => 'compet-scenario-reset-reminder',
                    'label' => 'Si besoin en test : reset-reminder (efface pushReminderSentAt).',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'compet-scenario-14h-reminder',
                    'label' => '14:00 — step reminder : relance des 3 joueurs sans ligne Pronostic.',
                    'kind' => 'action',
                    'route' => 'admin_pronostic_reminders_history',
                    'route_label' => 'Historique relances',
                ],
                [
                    'id' => 'compet-scenario-15h-kickoff',
                    'label' => '15:00 — step kickoff : LIVE 0-0, pronos 0-0 auto pour les oublis.',
                    'kind' => 'action',
                    'route' => 'app_matches',
                    'route_label' => 'Matchs',
                ],
                [
                    'id' => 'compet-scenario-23-goal-fr1',
                    'label' => '15:23 — step goal : 1er but France (23′) → score 1-0.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'compet-scenario-45-goal-de',
                    'label' => '15:45 — step goal : but Allemagne (45′) → score 1-1.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'compet-scenario-67-goal-fr2',
                    'label' => '16:07 — step goal : 2e but France (67′) → score 2-1.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'compet-scenario-17h-finish',
                    'label' => '17:05 — step finish : FINISHED, finalisation, classement.',
                    'kind' => 'action',
                    'route' => 'app_ranking',
                    'route_label' => 'Classement',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionScenarioVerification(): array
    {
        return [
            'id' => 'scenario-verify',
            'title' => 'Scénario FR–DE — Vérifications finales',
            'icon' => 'ti-checkbox',
            'description' => 'Contrôler points pronos, buteurs et classement des 3 équipes (score final 2-1).',
            'items' => [
                [
                    'id' => 'compet-scenario-verify-reminder-count',
                    'label' => 'Relances : 3 joueurs ciblés (équipes B-J2, C-J1, C-J2).',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'compet-scenario-verify-prono-matrix',
                    'label' => 'Matrice pronos : A-J1 exact, A-J2 bon 1N2, B-J1 bon 1N2, B-J2/C mauvais.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'compet-scenario-verify-buteur-points',
                    'label' => 'Points buteur : crédités pour buts FR choisis, pas pour but DE non sélectionné.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'compet-scenario-verify-ranking',
                    'label' => 'Classement : 3 équipes classées, évolution journée cohérente.',
                    'kind' => 'attention',
                    'route' => 'app_ranking',
                    'route_label' => 'Classement',
                ],
                [
                    'id' => 'compet-scenario-verify-finalized',
                    'label' => 'Admin match : liveScoresFinalizedAt renseigné, statut Terminé.',
                    'kind' => 'attention',
                    'route' => 'admin_game_match_index',
                    'route_label' => 'Fiche match',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionPronostiques(): array
    {
        return [
            'id' => 'pronostiques',
            'title' => 'Pronostiques (général)',
            'icon' => 'ti-ball-football',
            'description' => 'Comportement hors scénario : verrouillage, partenaire, liste.',
            'items' => [
                [
                    'id' => 'compet-prono-lock-kickoff',
                    'label' => 'Après CO ou statut ≠ Programmé : plus de modification prono.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'compet-prono-partner-visible',
                    'label' => 'Prono du partenaire visible sur la carte match.',
                    'kind' => 'attention',
                    'route' => 'app_homepage',
                    'route_label' => 'Accueil',
                ],
                [
                    'id' => 'compet-prono-finished-detail',
                    'label' => 'Match terminé : lien « Voir tous les pronos » fonctionnel.',
                    'kind' => 'action',
                    'route' => 'app_pronostics',
                    'route_label' => 'Pronostics',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionJokers(): array
    {
        return [
            'id' => 'jokers',
            'title' => 'Jokers',
            'icon' => 'ti-wand',
            'description' => 'Si jokers placés sur le match test, vérifier leurs effets après finish.',
            'items' => [
                [
                    'id' => 'compet-joker-effects',
                    'label' => 'Effets jokers (inverse, pique, bouclier, collecte, buteur ×2) conformes au règlement.',
                    'kind' => 'attention',
                    'route' => 'app_jokers',
                    'route_label' => 'Catalogue',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionPointsEtClassement(): array
    {
        return [
            'id' => 'points-classement',
            'title' => 'Points et classement',
            'icon' => 'ti-chart-bar',
            'description' => 'Règles de calcul appliquées pendant le scénario.',
            'items' => [
                [
                    'id' => 'compet-points-exact',
                    'label' => 'Score exact : points de base × cote.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'compet-points-good-result',
                    'label' => 'Bon 1N2 sans exact : points bon résultat.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'compet-live-rescore-on-goal',
                    'label' => 'Chaque but : rescoring prono immédiat avant finish.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'compet-rank-order',
                    'label' => 'Ordre classement : pts → exacts → bons 1N2 → risque → nom.',
                    'kind' => 'attention',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionNotifications(): array
    {
        return [
            'id' => 'notifications',
            'title' => 'Notifications',
            'icon' => 'ti-bell',
            'description' => 'Relances et buts du scénario.',
            'items' => [
                [
                    'id' => 'compet-notif-prono-reminder',
                    'label' => 'Relances 14h reçues par les joueurs sans prono (e-mail / push).',
                    'kind' => 'attention',
                    'route' => 'admin_pronostic_reminders_history',
                    'route_label' => 'Historique',
                ],
                [
                    'id' => 'compet-notif-buteur-goal',
                    'label' => 'But buteur choisi (23′ ou 67′) : notification push si abonné.',
                    'kind' => 'attention',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionControlesAdmin(): array
    {
        return [
            'id' => 'admin',
            'title' => 'Référence technique',
            'icon' => 'ti-terminal-2',
            'description' => 'Commandes et crons du scénario manuel.',
            'items' => [
                [
                    'id' => 'compet-admin-cli-info',
                    'label' => 'Commande info : app:test-match:step --match-id=… --step=info',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'compet-admin-cron-endpoint',
                    'label' => 'Cron HTTP : /cron/test-match-step?token=…&match_id=…&step=…',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'compet-admin-script-sh',
                    'label' => 'Script : scripts/test-match-scenario.sh (après test-match-scenario.env).',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'compet-checklist-meta',
                    'label' => 'Cases = localStorage · Notes = partagées entre admins.',
                    'kind' => 'attention',
                ],
            ],
        ];
    }
}
