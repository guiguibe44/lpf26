<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Textes intro site et joker double équipe : cotes à 2 décimales, points arrondis à l’entier.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE site_intro_slide
            SET body = '<p><strong>Score exact</strong> = 30 pts de base, <strong>bon 1/N/2</strong> = 10 pts, sinon 0. Les <strong>cotes</strong> (1, N, 2) montent quand peu de monde parie sur ce résultat — plafond ×5, arrondies à <strong>2 décimales</strong>.</p><p><strong>Points finaux</strong> = base × cote, <strong>arrondis à l’entier</strong>. Les cotes apparaissent au coup d’envoi.</p>'
            WHERE visual_theme = 'points'
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE site_intro_slide
            SET body = '<p>Chaque joueur cotisé choisit un <strong>buteur</strong> avant le verrouillage. À chaque but en CDM : <strong>10 pts × cote buteur</strong> (cote à 2 décimales, points arrondis à l’entier, plafond ×5).</p><p>Les points buteur s’ajoutent au classement équipe.</p>'
            WHERE visual_theme = 'buteur'
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE joker
            SET technical_explanation = 'À poser sur un match à venir, avant le coup d''envoi.
            Chaque joueur est noté sur sa propre cote (×2 sur le barème du match, arrondi à l''entier).
            Score exact : arrondi(30 × cote × 2). Bon résultat (sans score exact) : arrondi(10 × cote × 2).
            Mauvais résultat : −arrondi(3 × cote) (cote individuelle du pronostic, 2 décimales).
            Le total équipe sur le match est la somme des deux joueurs.'
            WHERE code = 'double_equipe'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE site_intro_slide
            SET body = '<p><strong>Score exact</strong> = 30 pts de base, <strong>bon 1/N/2</strong> = 10 pts, sinon 0. Les <strong>cotes</strong> (1, N, 2) montent quand peu de monde parie sur ce résultat — plafond ×5.</p><p>Points finaux = base × cote. Les cotes apparaissent au coup d’envoi.</p>'
            WHERE visual_theme = 'points'
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE site_intro_slide
            SET body = '<p>Chaque joueur cotisé choisit un <strong>buteur</strong> avant le verrouillage. À chaque but en CDM : <strong>10 pts × cote buteur</strong> (choix rare = gros multiplicateur, plafond ×5).</p><p>Les points buteur s’ajoutent au classement équipe.</p>'
            WHERE visual_theme = 'buteur'
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE joker
            SET technical_explanation = 'À poser sur un match à venir, avant le coup d''envoi.
            Chaque joueur est noté sur sa propre cote (×2 sur le barème du match).
            Score exact : 30 × cote × 2. Bon résultat (sans score exact) : 10 × cote × 2.
            Mauvais résultat : −3 × cote (cote individuelle du pronostic).
            Le total équipe sur le match est la somme des deux joueurs.'
            WHERE code = 'double_equipe'
            SQL);
    }
}
