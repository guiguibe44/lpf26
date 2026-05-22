<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260522120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Slides de présentation du site (EasyAdmin) + contenu initial.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE site_intro_slide (
                id INT AUTO_INCREMENT NOT NULL,
                sort_order INT DEFAULT 0 NOT NULL,
                active TINYINT(1) DEFAULT 1 NOT NULL,
                title VARCHAR(255) NOT NULL,
                eyebrow VARCHAR(128) DEFAULT NULL,
                body LONGTEXT DEFAULT NULL,
                image VARCHAR(255) DEFAULT NULL,
                icon VARCHAR(64) DEFAULT NULL,
                visual_theme VARCHAR(32) DEFAULT 'welcome' NOT NULL,
                visual_badge VARCHAR(64) DEFAULT NULL,
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function postUp(Schema $schema): void
    {
        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM site_intro_slide');
        if ($count > 0) {
            return;
        }

        $slides = [
            [0, 1, 'Le foot, à deux, en mode compét’', 'Mode d’emploi express', 'welcome', null, 'users', '<p>LPF\'26, c’est la Coupe du monde version pronos : scores, cotes, buteur, jokers et classement. Cette mini-visite te donne l’essentiel — version fun, pas tribunal.</p>'],
            [10, 1, 'Une équipe, deux cerveaux', null, 'team', null, 'users', '<p>Vous êtes <strong>deux joueurs max</strong> par équipe : création, surnoms, logo, invitation du partenaire. Tout se gère dans <a href="/mon-compte">Mon compte</a>.</p><p><em>Cotisation réglée = pronos et buteur débloqués.</em></p>'],
            [20, 1, 'Prono time', null, 'prono', null, 'ball-football', '<p>Sur chaque match à venir, saisis un <strong>score domicile — extérieur</strong> (ex. 2-1). Tu peux modifier tant que le match n’a pas commencé.</p><p>Tu vois le prono de ton partenaire sur la carte ; le détail public arrive après le match.</p>'],
            [30, 1, 'Multiplie tes points', null, 'points', '× cote', 'chart-line', '<p><strong>Score exact</strong> ≈ 3 pts de base, <strong>bon 1/N/2</strong> ≈ 1 pt, sinon 0. Les <strong>cotes</strong> (1, N, 2) montent quand peu de monde parie sur ce résultat — plafond ×5.</p><p>Points finaux = base × cote. Les cotes apparaissent au coup d’envoi.</p>'],
            [40, 1, 'Bigballs — le duo qui ose', null, 'bigballs', null, 'flame', '<p>Même score pour les <strong>deux coéquipiers</strong> sur un match ? Vous tentez un <strong>bigballs</strong>. Réussi si le score colle (exact ou bon 1/N/2).</p><p>En cas d’égalité au classement, ça peut faire la différence.</p>'],
            [50, 1, 'Ton buteur star', null, 'buteur', null, 'target', '<p>Chaque joueur cotisé choisit un <strong>buteur</strong> avant le verrouillage. À chaque but en CDM : <strong>1 pt × cote buteur</strong> (choix rare = gros multiplicateur).</p><p>Les points buteur s’ajoutent au classement équipe.</p>'],
            [60, 1, 'Jokers magiques', null, 'jokers', null, 'wand', '<p>Chaque type de joker : <strong>une fois par compétition</strong>, un joker par match, posé avant le coup d’envoi. Certains peuvent être neutralisés (bouclier, équipe favorite…).</p><p><a href="/jokers">Guide des jokers</a></p>'],
            [70, 1, 'Monte au classement', null, 'ranking', null, 'trophy', '<p>Classement par <strong>équipe</strong> : pronos + buteur + effets jokers. Consulte le <a href="/classement">classement</a>, les <a href="/groupes">groupes</a> et le <a href="/forum">forum</a>.</p><p>Relances e-mail / push si tu oublies un prono (réglable dans Mon compte).</p>'],
            [80, 1, 'À vous de jouer !', null, 'go', null, 'confetti', '<p>Tu peux rouvrir cette présentation via l’icône lecture dans la barre latérale. Pour le détail juridique, le <a href="/reglement">règlement complet</a> reste la référence.</p><p><a href="/matchs">Voir les matchs</a></p>'],
        ];

        foreach ($slides as [$order, $active, $title, $eyebrow, $theme, $badge, $icon, $body]) {
            $this->connection->insert('site_intro_slide', [
                'sort_order' => $order,
                'active' => $active,
                'title' => $title,
                'eyebrow' => $eyebrow,
                'body' => $body,
                'image' => null,
                'icon' => $icon,
                'visual_theme' => $theme,
                'visual_badge' => $badge,
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE site_intro_slide');
    }
}
