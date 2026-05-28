<?php

declare(strict_types=1);

namespace App\Service;

use App\Data\AdminQaChecklistDescriptions;

/**
 * Checklist de recette manuelle réservée aux administrateurs.
 *
 * @phpstan-type QaItem array{
 *     id: string,
 *     label: string,
 *     description: string,
 *     kind: 'action'|'attention',
 *     route?: string,
 *     route_label?: string,
 * }
 * @phpstan-type QaSection array{
 *     id: string,
 *     title: string,
 *     icon: string,
 *     description: string,
 *     items: list<QaItem>,
 * }
 */
final class AdminQaChecklistProvider
{
    /**
     * @return list<QaSection>
     */
    public function getSections(): array
    {
        $sections = [
            $this->sectionInscription(),
            $this->sectionConnexion(),
            $this->sectionMotDePasse(),
            $this->sectionFinalisationCompte(),
            $this->sectionFinalisationEquipe(),
            $this->sectionCotisation(),
            $this->sectionNavigation(),
            $this->sectionAccueil(),
            $this->sectionPronostiques(),
            $this->sectionJokers(),
            $this->sectionPoints(),
            $this->sectionClassement(),
            $this->sectionForum(),
            $this->sectionNotifications(),
            $this->sectionAdministration(),
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
        $descriptions = AdminQaChecklistDescriptions::all();

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
    private function sectionInscription(): array
    {
        return [
            'id' => 'inscription',
            'title' => 'Inscription',
            'icon' => 'ti-user-plus',
            'description' => 'Création du premier joueur, de l’équipe et envoi des e-mails.',
            'items' => [
                [
                    'id' => 'inscription-form-validation',
                    'label' => 'Tester le formulaire : champs obligatoires, e-mails invalides, mots de passe trop courts.',
                    'kind' => 'action',
                    'route' => 'app_register',
                    'route_label' => 'Page inscription',
                ],
                [
                    'id' => 'inscription-team-created',
                    'label' => 'Vérifier qu’une équipe est bien créée avec le nom saisi et le joueur 1 comme membre.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'inscription-confirmation-email',
                    'label' => 'Contrôler la réception de l’e-mail de confirmation (lien valide, expiration).',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'inscription-partner-invite',
                    'label' => 'Avec e-mail partenaire renseigné : vérifier l’envoi de l’invitation et le délai de validité (3 jours).',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'inscription-duplicate-email',
                    'label' => 'Tenter une inscription avec un e-mail déjà utilisé : message d’erreur clair.',
                    'kind' => 'action',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionConnexion(): array
    {
        return [
            'id' => 'connexion',
            'title' => 'Connexion et déconnexion',
            'icon' => 'ti-login',
            'description' => 'Authentification, session et accès aux pages protégées.',
            'items' => [
                [
                    'id' => 'login-success',
                    'label' => 'Se connecter avec identifiants valides → redirection vers l’accueil.',
                    'kind' => 'action',
                    'route' => 'app_login',
                    'route_label' => 'Page connexion',
                ],
                [
                    'id' => 'login-invalid',
                    'label' => 'Identifiants incorrects : message d’erreur sans fuite d’information.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'login-remember-me',
                    'label' => 'Cocher « Se souvenir de moi », fermer le navigateur, rouvrir : session toujours active.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'login-guest-redirect',
                    'label' => 'En tant qu’invité, accéder à /matchs ou /pronostics → redirection vers /login.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'logout',
                    'label' => 'Déconnexion depuis la sidebar : plus d’accès aux pages protégées.',
                    'kind' => 'action',
                    'route' => 'app_logout',
                    'route_label' => 'Déconnexion',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionMotDePasse(): array
    {
        return [
            'id' => 'mot-de-passe',
            'title' => 'Mot de passe oublié',
            'icon' => 'ti-key',
            'description' => 'Parcours reset-password et changement depuis le compte.',
            'items' => [
                [
                    'id' => 'forgot-request',
                    'label' => 'Demander une réinitialisation : page « vérifiez vos e-mails » même si l’e-mail est inconnu.',
                    'kind' => 'action',
                    'route' => 'app_forgot_password_request',
                    'route_label' => 'Mot de passe oublié',
                ],
                [
                    'id' => 'forgot-email-received',
                    'label' => 'Vérifier l’e-mail reçu (lien, délai d’expiration du token).',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'forgot-reset-form',
                    'label' => 'Définir un nouveau mot de passe via le lien : connexion possible ensuite.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'account-password-change',
                    'label' => 'Depuis Mon compte : changer le mot de passe (ancien mot de passe requis).',
                    'kind' => 'action',
                    'route' => 'app_account',
                    'route_label' => 'Mon compte',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionFinalisationCompte(): array
    {
        return [
            'id' => 'finalisation-compte',
            'title' => 'Finalisation du compte',
            'icon' => 'ti-user-check',
            'description' => 'Profil joueur, avatar et paramètres personnels.',
            'items' => [
                [
                    'id' => 'account-avatar-upload',
                    'label' => 'Uploader un avatar (formats autorisés, taille max) et vérifier l’affichage sidebar / forum.',
                    'kind' => 'action',
                    'route' => 'app_account',
                    'route_label' => 'Mon compte',
                ],
                [
                    'id' => 'account-nickname',
                    'label' => 'Modifier le surnom : reflété sur les cartes match et mentions forum.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'account-email-readonly',
                    'label' => 'L’e-mail n’est pas modifiable par le joueur (sauf admin).',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'account-precomp-progress',
                    'label' => 'Avant la compétition : la checklist « Avant la compétition » sur l’accueil se met à jour.',
                    'kind' => 'attention',
                    'route' => 'app_homepage',
                    'route_label' => 'Accueil',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionFinalisationEquipe(): array
    {
        return [
            'id' => 'finalisation-equipe',
            'title' => 'Finalisation de l’équipe',
            'icon' => 'ti-users',
            'description' => 'Partenaire, logo, slogan, pays favori et verrouillages.',
            'items' => [
                [
                    'id' => 'team-invite-partner',
                    'label' => 'Inviter un partenaire par e-mail si l’équipe n’a qu’un joueur.',
                    'kind' => 'action',
                    'route' => 'app_account',
                    'route_label' => 'Mon compte',
                ],
                [
                    'id' => 'team-accept-invitation',
                    'label' => 'Accepter une invitation via /invitation/{token} : le joueur 2 rejoint l’équipe.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'team-logo-slogan',
                    'label' => 'Définir logo et slogan d’équipe : affichage sur fiche équipe et classement.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'team-favorite-country',
                    'label' => 'Choisir l’équipe favorite (pays) : impact joker « équipe favorite » en phase de groupes.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'team-two-players-max',
                    'label' => 'Impossible d’ajouter un 3ᵉ joueur à l’équipe.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'team-lock-after-start',
                    'label' => 'Après début de compétition : nom d’équipe et buteur verrouillés côté joueur.',
                    'kind' => 'attention',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionCotisation(): array
    {
        return [
            'id' => 'cotisation',
            'title' => 'Cotisation',
            'icon' => 'ti-coin',
            'description' => 'Blocage des fonctionnalités tant que la cotisation n’est pas validée.',
            'items' => [
                [
                    'id' => 'cotisation-banner',
                    'label' => 'Joueur non cotisé : bandeau rouge visible sur toutes les pages.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'cotisation-prono-blocked',
                    'label' => 'Sans cotisation : formulaires de prono grisés / message explicite.',
                    'kind' => 'action',
                    'route' => 'app_matches',
                    'route_label' => 'Matchs',
                ],
                [
                    'id' => 'cotisation-buteur-blocked',
                    'label' => 'Sans cotisation : choix du buteur bloqué sur l’accueil et le compte.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'cotisation-admin-enable',
                    'label' => 'Activer cotisationPayee en admin : déblocage immédiat après rechargement.',
                    'kind' => 'action',
                    'route' => 'admin_user_index',
                    'route_label' => 'Utilisateurs (admin)',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionNavigation(): array
    {
        return [
            'id' => 'navigation',
            'title' => 'Navigation',
            'icon' => 'ti-layout-sidebar',
            'description' => 'Menu latéral, mobile et cohérence des liens.',
            'items' => [
                [
                    'id' => 'nav-sidebar-links',
                    'label' => 'Vérifier chaque entrée du menu : Accueil, Matchs, Groupes, Classement, Jokers, Forum, Règlement, Compte.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'nav-active-state',
                    'label' => 'L’entrée active est bien mise en évidence selon la page courante.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'nav-mobile-burger',
                    'label' => 'Sur mobile : ouverture / fermeture du menu, fermeture au clic sur un lien.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'nav-admin-link',
                    'label' => 'L’icône admin n’apparaît que pour ROLE_ADMIN.',
                    'kind' => 'attention',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionAccueil(): array
    {
        return [
            'id' => 'accueil',
            'title' => 'Accueil',
            'icon' => 'ti-home',
            'description' => 'Tableau de bord matchs, buteur et contenus pré-compétition.',
            'items' => [
                [
                    'id' => 'home-match-sections',
                    'label' => 'Sections affichées : matchs en direct, dernière journée terminée, prochaine journée.',
                    'kind' => 'attention',
                    'route' => 'app_homepage',
                    'route_label' => 'Accueil',
                ],
                [
                    'id' => 'home-buteur-stats',
                    'label' => 'Après début compétition : bloc stats du buteur choisi visible.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'home-partner-pronos',
                    'label' => 'Sur chaque carte : pronos du partenaire visibles côte à côte.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'home-empty-state',
                    'label' => 'Sans match planifié : message « Aucun match à afficher » correct.',
                    'kind' => 'attention',
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
            'title' => 'Pronostiques',
            'icon' => 'ti-ball-football',
            'description' => 'Saisie, modification, verrouillage et affichage des scores.',
            'items' => [
                [
                    'id' => 'prono-matches-page',
                    'label' => 'Saisir un score sur /matchs : enregistrement et affichage immédiat.',
                    'kind' => 'action',
                    'route' => 'app_matches',
                    'route_label' => 'Matchs',
                ],
                [
                    'id' => 'prono-list-page',
                    'label' => 'Vue liste /pronostics : tous les matchs et pronos du joueur.',
                    'kind' => 'action',
                    'route' => 'app_pronostics',
                    'route_label' => 'Pronostics',
                ],
                [
                    'id' => 'prono-lock-kickoff',
                    'label' => 'Impossible de modifier après coup d’envoi ou si statut ≠ SCHEDULED.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'prono-default-scores',
                    'label' => 'Nouveau joueur cotisé : pronos par défaut créés pour les matchs à venir.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'prono-finished-detail',
                    'label' => 'Match terminé : lien « Voir tous les pronos » → page détail publique.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'prono-risk-taking',
                    'label' => 'Deux joueurs même équipe, même score : indicateur « BigBalls ».',
                    'kind' => 'attention',
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
            'description' => 'Catalogue, placement sur match et effets défensifs.',
            'items' => [
                [
                    'id' => 'joker-catalog',
                    'label' => 'Page /jokers : catalogue complet, descriptions et images.',
                    'kind' => 'action',
                    'route' => 'app_jokers',
                    'route_label' => 'Jokers',
                ],
                [
                    'id' => 'joker-drawer-open',
                    'label' => 'Sur une carte match : ouvrir le drawer, état JSON chargé sans erreur.',
                    'kind' => 'action',
                    'route' => 'app_matches',
                    'route_label' => 'Matchs',
                ],
                [
                    'id' => 'joker-place-remove',
                    'label' => 'Placer puis retirer un joker (avant verrouillage) : badge et état mis à jour.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'joker-target-team',
                    'label' => 'Jokers offensifs (pique points, inverse score/buteur) : sélection équipe cible obligatoire.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'joker-bouclier',
                    'label' => 'Bouclier : équipe protégée sur la journée ; joker offensif neutralisé.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'joker-espion',
                    'label' => 'Espion : confirmation avant placement ; intel visible sur la carte match.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'joker-once-per-type',
                    'label' => 'Chaque type de joker utilisable une seule fois par équipe sur la compétition.',
                    'kind' => 'attention',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionPoints(): array
    {
        return [
            'id' => 'points',
            'title' => 'Points, cotes et buteur',
            'icon' => 'ti-chart-bar',
            'description' => 'Calcul des points prono, cotes et points buteur.',
            'items' => [
                [
                    'id' => 'points-exact-score',
                    'label' => 'Score exact : arrondi(base × cote) — cote à 2 décimales (défaut 30 pts de base, modifiable par match).',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'points-good-result',
                    'label' => 'Bon 1N2 sans score exact : arrondi(10 × cote) par défaut.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'points-cote-display',
                    'label' => 'Cotes affichées à 2 décimales (1/N/2 ou min/moy/max selon le mode).',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'points-buteur-goal',
                    'label' => 'But du buteur choisi : arrondi(10 × cote à 2 décimales, plafond ×5).',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'points-rescore-match',
                    'label' => 'Saisir score réel en admin ou sync : rescoring automatique des pronos concernés.',
                    'kind' => 'action',
                    'route' => 'admin_game_match_index',
                    'route_label' => 'Matchs (admin)',
                ],
                [
                    'id' => 'points-rescore-buteur-cmd',
                    'label' => 'Après import buts : lancer app:rescore:buteur-goals si besoin.',
                    'kind' => 'attention',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionClassement(): array
    {
        return [
            'id' => 'classement',
            'title' => 'Classement',
            'icon' => 'ti-trophy',
            'description' => 'Classement équipes, évolution et groupes des pays.',
            'items' => [
                [
                    'id' => 'ranking-order',
                    'label' => 'Ordre : points totaux → scores exacts → bons résultats → BigBalls → nom.',
                    'kind' => 'attention',
                    'route' => 'app_ranking',
                    'route_label' => 'Classement',
                ],
                [
                    'id' => 'ranking-evolution',
                    'label' => 'Graphique / évolution par journée cohérent après chaque match terminé.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'ranking-team-page',
                    'label' => 'Fiche équipe : membres, historique, stats agrégées.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'groups-page',
                    'label' => 'Page Groupes des pays : classements de poules à jour.',
                    'kind' => 'action',
                    'route' => 'app_groups',
                    'route_label' => 'Groupes',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionForum(): array
    {
        return [
            'id' => 'forum',
            'title' => 'Forum',
            'icon' => 'ti-messages',
            'description' => 'Messages, mentions, modération et panneau latéral.',
            'items' => [
                [
                    'id' => 'forum-post',
                    'label' => 'Publier un message racine : affiché dans le fil récent.',
                    'kind' => 'action',
                    'route' => 'app_forum',
                    'route_label' => 'Forum',
                ],
                [
                    'id' => 'forum-reply',
                    'label' => 'Répondre à un fil : hiérarchie et compteur de réponses corrects.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'forum-mention',
                    'label' => 'Mention @joueur : autocomplétion et notification au destinataire.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'forum-edit-delete',
                    'label' => 'Éditer / supprimer son message ; admin peut modérer depuis EasyAdmin.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'forum-panel-mobile',
                    'label' => 'Panneau forum (slide) : ouverture depuis la cloche / mobile sans casser le layout.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'forum-sanitizer',
                    'label' => 'Tenter du HTML brut ou script : contenu assaini / rejeté.',
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
            'description' => 'Push, e-mails automatiques et relances pronostic.',
            'items' => [
                [
                    'id' => 'push-subscribe',
                    'label' => 'Activer les notifications push depuis Mon compte (navigateur compatible).',
                    'kind' => 'action',
                    'route' => 'app_account',
                    'route_label' => 'Mon compte',
                ],
                [
                    'id' => 'push-forum-mention',
                    'label' => 'Mention forum : notification reçue si abonné.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'prono-reminder-cron',
                    'label' => 'Relance auto 1 h avant match (ou 22 h veille) : vérifier historique admin.',
                    'kind' => 'attention',
                    'route' => 'admin_pronostic_reminders_history',
                    'route_label' => 'Historique relances',
                ],
                [
                    'id' => 'manual-message-admin',
                    'label' => 'Message manuel admin : push et/ou e-mail reçus par les joueurs ciblés.',
                    'kind' => 'action',
                    'route' => 'admin_manual_messages',
                    'route_label' => 'Messages manuels',
                ],
            ],
        ];
    }

    /**
     * @return QaSection
     */
    private function sectionAdministration(): array
    {
        return [
            'id' => 'administration',
            'title' => 'Administration',
            'icon' => 'ti-settings',
            'description' => 'Back-office EasyAdmin, rôles et synchronisation données.',
            'items' => [
                [
                    'id' => 'admin-access',
                    'label' => 'ROLE_ADMIN : accès /admin ; joueur standard : refus 403.',
                    'kind' => 'attention',
                    'route' => 'admin',
                    'route_label' => 'EasyAdmin',
                ],
                [
                    'id' => 'admin-super-sync',
                    'label' => 'Sync API WC2026 : réservée ROLE_SUPER_ADMIN uniquement.',
                    'kind' => 'attention',
                ],
                [
                    'id' => 'admin-match-score',
                    'label' => 'Modifier score réel d’un match : déclenche rescoring + snapshot classement.',
                    'kind' => 'action',
                ],
                [
                    'id' => 'admin-invitation-renew',
                    'label' => 'Renouveler une invitation expirée depuis l’admin.',
                    'kind' => 'action',
                    'route' => 'admin_team_invitation_index',
                    'route_label' => 'Invitations',
                ],
                [
                    'id' => 'admin-qa-checklist',
                    'label' => 'Cette checklist : progression sauvegardée localement (navigateur). Réinitialiser si besoin.',
                    'kind' => 'attention',
                    'route' => 'admin_qa_checklist',
                    'route_label' => 'Checklist QA',
                ],
            ],
        ];
    }
}
