<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Barème buteur : paliers 50 / 40 / 30 / 20 / 10 pts selon popularité.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE site_intro_slide
            SET body = '<p>Chaque joueur cotisé choisit un <strong>buteur</strong> avant le verrouillage. À chaque but en CDM : <strong>50 / 40 / 30 / 20 / 10 pts</strong> selon le nombre de joueurs l’ayant choisi (1 / 2 / 3-4 / 5-7 / 8+).</p><p>Les points buteur s’ajoutent au classement équipe.</p>'
            WHERE visual_theme = 'buteur'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE site_intro_slide
            SET body = '<p>Chaque joueur cotisé choisit un <strong>buteur</strong> avant le verrouillage. À chaque but en CDM : <strong>10 pts × cote buteur</strong> (cote à 2 décimales, points arrondis à l’entier, plafond ×5).</p><p>Les points buteur s’ajoutent au classement équipe.</p>'
            WHERE visual_theme = 'buteur'
            SQL);
    }
}
