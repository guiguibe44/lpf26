<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Descriptions détaillées des points de la checklist QA (aide à la recette).
 */
final class AdminQaChecklistDescriptions
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'inscription-form-validation' => 'Parcourir tous les champs obligatoires, formats e-mail, règles de mot de passe Symfony. Vérifier les messages d’erreur en français.',
            'inscription-team-created' => 'Après inscription réussie : contrôler en admin (Équipes) que l’équipe existe et que le joueur 1 est bien membre avec le bon surnom.',
            'inscription-confirmation-email' => 'Vérifier la boîte mail (ou Mailpit en local), cliquer le lien de vérification, confirmer que le compte est activé.',
            'inscription-partner-invite' => 'Saisir l’e-mail du joueur 2 à l’inscription : l’invitation doit arriver, le lien /invitation/{token} doit fonctionner, expiration 3 jours.',
            'inscription-duplicate-email' => 'Réutiliser un e-mail déjà inscrit : pas de doublon en base, message utilisateur explicite.',

            'login-success' => 'Connexion avec un compte valide : redirection vers l’accueil, menu latéral visible, pas d’erreur flash.',
            'login-invalid' => 'Mauvais mot de passe ou e-mail inconnu : message générique (« identifiants invalides »), pas d’indication sur l’existence du compte.',
            'login-remember-me' => 'Cocher la case, fermer complètement le navigateur, rouvrir : toujours connecté (cookie remember_me 7 jours).',
            'login-guest-redirect' => 'Sans session, ouvrir /matchs ou /pronostics : redirection 302 vers /login.',
            'logout' => 'Déconnexion via la sidebar : session détruite, accès aux pages protégées impossible sans reconnecter.',

            'forgot-request' => 'Saisir un e-mail (existant ou non) : toujours la même page « consultez vos e-mails » (pas d’énumération de comptes).',
            'forgot-email-received' => 'Contrôler le mail de reset : lien unique, token expiré après délai configuré, mise en forme LPF.',
            'forgot-reset-form' => 'Définir un nouveau mot de passe via le lien, puis se connecter avec le nouveau mot de passe.',
            'account-password-change' => 'Mon compte → changement de mot de passe : ancien mot de passe requis, confirmation du nouveau.',

            'account-avatar-upload' => 'Tester JPG/PNG/WebP/GIF dans la limite (4 Mo), affichage dans la sidebar et à côté des messages forum.',
            'account-nickname' => 'Modifier le surnom : visible sur les cartes match, classement et @mentions forum.',
            'account-email-readonly' => 'Le joueur ne peut pas modifier son e-mail depuis le front ; seul l’admin peut le faire.',
            'account-precomp-progress' => 'Avant le coup d’envoi : les cartes « Avant la compétition » sur l’accueil passent au vert au fur et à mesure des actions.',

            'team-invite-partner' => 'Équipe à 1 joueur : envoyer une invitation par e-mail depuis Mon compte, vérifier la réception.',
            'team-accept-invitation' => 'Joueur 2 : ouvrir le lien d’invitation, créer son compte, vérifier qu’il apparaît dans l’équipe (2 membres max.).',
            'team-logo-slogan' => 'Uploader un logo et un slogan : visibles sur la fiche équipe et dans le classement.',
            'team-favorite-country' => 'Choisir un pays favori : utilisé pour le joker « équipe favorite » en phase de groupes uniquement.',
            'team-two-players-max' => 'Tenter d’inviter un 3ᵉ joueur ou une 2ᵉ invitation alors que l’équipe est complète : refus ou message clair.',
            'team-lock-after-start' => 'Une fois la compétition démarrée : le joueur ne peut plus changer le nom d’équipe ni son buteur.',

            'cotisation-banner' => 'Compte sans cotisationPayee : bandeau rouge persistant sur toutes les pages du shell connecté.',
            'cotisation-prono-blocked' => 'Sans cotisation : champs de score désactivés ou message, pas d’enregistrement possible.',
            'cotisation-buteur-blocked' => 'Sans cotisation : sélection du buteur impossible sur l’accueil et Mon compte.',
            'cotisation-admin-enable' => 'Cocher cotisation payée en admin utilisateur : après refresh, pronos et buteur débloqués.',

            'nav-sidebar-links' => 'Cliquer chaque lien du menu : Accueil, Matchs, Groupes, Classement, Jokers, Forum, Règlement, Compte — pas de 404.',
            'nav-active-state' => 'Sur chaque page, l’entrée correspondante du menu doit être visuellement active.',
            'nav-mobile-burger' => 'Viewport &lt; 768px : menu burger, overlay, fermeture au tap sur un lien ou le fond.',
            'nav-admin-link' => 'Compte joueur standard : pas d’icône engrenage/liste admin. Compte ROLE_ADMIN : icônes visibles.',

            'home-match-sections' => 'Vérifier les blocs « en direct », « dernière journée terminée » et « prochaine journée » selon les données en base.',
            'home-buteur-stats' => 'Compétition démarrée + buteur choisi : résumé des points buteur affiché en haut de l’accueil.',
            'home-partner-pronos' => 'Sur chaque carte match : affichage du prono du joueur et de celui du partenaire (même équipe).',
            'home-empty-state' => 'Aucun match en base ou hors calendrier : message vide cohérent, pas d’erreur PHP.',

            'prono-matches-page' => 'Saisir scores domicile/extérieur sur /matchs, enregistrer : valeurs persistées au rechargement.',
            'prono-list-page' => 'Page /pronostics : liste complète des matchs avec les pronos du joueur connecté.',
            'prono-lock-kickoff' => 'Après heure de coup d’envoi ou statut match ≠ SCHEDULED : formulaire verrouillé.',
            'prono-default-scores' => 'Nouveau joueur cotisé : commande ou service crée des pronos par défaut sur les matchs à venir.',
            'prono-finished-detail' => 'Match FINISHED : lien vers la page publique listant tous les pronos du match.',
            'prono-risk-taking' => 'Les deux joueurs d’une équipe pronostiquent le même score : badge ou mention « BigBalls ».',

            'joker-catalog' => 'Page /jokers : tous les types listés avec image, titre et description réglementaire.',
            'joker-drawer-open' => 'Sur une carte match : ouvrir le tiroir joker, chargement JSON sans erreur console.',
            'joker-place-remove' => 'Poser un joker puis le retirer (tant que le match le permet) : badge et état synchronisés.',
            'joker-target-team' => 'Pique points / inverse score / inverse buteur : impossible sans choisir une équipe adverse.',
            'joker-bouclier' => 'Bouclier actif sur la journée : jokers offensifs contre cette équipe neutralisés au scoring.',
            'joker-espion' => 'Espion : modale de confirmation, puis affichage de l’intel sur la carte match.',
            'joker-once-per-type' => 'Tenter de rejouer le même code joker sur un autre match : refus avec message explicite.',

            'points-exact-score' => 'Match terminé, score exact prono : points = arrondi(pointsScoreExact × cote à 2 décimales) — défaut 30 pts de base.',
            'points-good-result' => 'Bon 1N2 sans score exact : pointsBonResultat (défaut 10 pts).',
            'points-cote-display' => 'Avant le match : cote min, moyenne et max affichées sur la carte pour le score saisi.',
            'points-buteur-goal' => 'But du buteur sélectionné : palier 50/40/30/20/10 pts selon popularité, cumulé au classement équipe.',
            'points-rescore-match' => 'Admin : saisir ou sync le score réel → recalcul automatique des pronos et snapshots classement.',
            'points-rescore-buteur-cmd' => 'Après import massif de buts : php bin/console app:rescore:buteur-goals si les coefficients semblent faux.',

            'ranking-order' => 'Vérifier l’ordre de tri : points totaux, puis exacts, bons résultats, BigBalls, nom d’équipe.',
            'ranking-evolution' => 'Après plusieurs matchs terminés : courbe ou tableau d’évolution par journée cohérent.',
            'ranking-team-page' => 'Cliquer une équipe : membres, historique des positions, totaux affichés.',
            'groups-page' => 'Page Groupes : classements des poules calculés à partir des matchs de phase de groupes.',

            'forum-post' => 'Publier un message racine : visible dans le fil, horodatage et auteur corrects.',
            'forum-reply' => 'Répondre dans un fil : indentation, ordre chronologique, compteur de réponses.',
            'forum-mention' => 'Taper @ + surnom : autocomplétion, notification push ou centre de notifs pour le destinataire.',
            'forum-edit-delete' => 'Auteur : éditer/supprimer son message. Admin : modération via EasyAdmin Messages forum.',
            'forum-panel-mobile' => 'Ouvrir le forum depuis le panneau latéral / mobile : pas de débordement ni de scroll bloqué.',
            'forum-sanitizer' => 'Poster du HTML/script : contenu nettoyé ou refusé, pas d’exécution XSS.',

            'push-subscribe' => 'Mon compte → activer push (HTTPS, VAPID configurés) : abonnement enregistré en base.',
            'push-forum-mention' => 'Avec push actif : mention @ → notification reçue sur l’appareil.',
            'prono-reminder-cron' => 'Cron app:push:pronostic-reminders : entrées dans Historique relances ; e-mail : max 1/jour/joueur/journée calendaire ; push : 1 par match.',
            'manual-message-admin' => 'Admin Messages manuels : envoi ciblé ou global, réception push et/ou e-mail selon choix.',

            'admin-access' => 'Joueur : /admin → 403. Admin : accès EasyAdmin. Super admin : menu sync API en plus.',
            'admin-super-sync' => 'Compte ROLE_ADMIN sans SUPER : liens sync WC2026 absents ou refusés. Super admin : accès OK.',
            'admin-match-score' => 'Modifier le score réel d’un match en admin : pronos rescoring + snapshots classement mis à jour.',
            'admin-invitation-renew' => 'Invitation expirée : renouveler depuis admin, nouveau lien fonctionnel sous 3 jours.',
            'admin-qa-checklist' => 'Cases cochées = localStorage par navigateur. Notes ci-dessous = partagées entre tous les admins en base.',
        ];
    }
}
