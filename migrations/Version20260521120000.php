<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute prises_risque_reussies au snapshot classement (si absente).';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('team_ranking_snapshot')->hasColumn('prises_risque_reussies')) {
            $this->addSql('ALTER TABLE team_ranking_snapshot ADD prises_risque_reussies INT DEFAULT 0 NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('team_ranking_snapshot')->hasColumn('prises_risque_reussies')) {
            $this->addSql('ALTER TABLE team_ranking_snapshot DROP prises_risque_reussies');
        }
    }
}
