<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507143300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le total des resultats faux dans l historique de classement des equipes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_ranking_snapshot ADD resultats_faux INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_ranking_snapshot DROP resultats_faux');
    }
}
