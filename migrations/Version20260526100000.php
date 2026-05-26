<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Points buteur : 10 pts de base par but (× cote, plafond ×5) et recalcul des buts existants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE but CHANGE points_base points_base INT DEFAULT 10 NOT NULL');

        $this->addSql(<<<'SQL'
            UPDATE but
            SET points_base = 10,
                points_attribues = ROUND(10 * COALESCE(cote_coefficient, 1))
            WHERE buteur_id IS NOT NULL
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE site_intro_slide
            SET body = '<p>Chaque joueur cotisé choisit un <strong>buteur</strong> avant le verrouillage. À chaque but en CDM : <strong>10 pts × cote buteur</strong> (choix rare = gros multiplicateur, plafond ×5).</p><p>Les points buteur s’ajoutent au classement équipe.</p>'
            WHERE visual_theme = 'buteur'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE but
            SET points_base = 1,
                points_attribues = ROUND(points_attribues / 10.0)
            WHERE buteur_id IS NOT NULL
            SQL);

        $this->addSql('ALTER TABLE but CHANGE points_base points_base INT DEFAULT 1 NOT NULL');

        $this->addSql(<<<'SQL'
            UPDATE site_intro_slide
            SET body = '<p>Chaque joueur cotisé choisit un <strong>buteur</strong> avant le verrouillage. À chaque but en CDM : <strong>1 pt × cote buteur</strong> (choix rare = gros multiplicateur).</p><p>Les points buteur s’ajoutent au classement équipe.</p>'
            WHERE visual_theme = 'buteur'
            SQL);
    }
}
