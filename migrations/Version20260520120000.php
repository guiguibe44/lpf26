<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le compteur des prises de risque reussies dans les snapshots de classement.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_ranking_snapshot ADD prises_risque_reussies INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_ranking_snapshot DROP prises_risque_reussies');
    }
}
