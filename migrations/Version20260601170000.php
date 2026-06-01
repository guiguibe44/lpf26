<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GIFs récap : table multi-fichiers par slot + repli depuis colonnes joker';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE team_recap_gif (id INT AUTO_INCREMENT NOT NULL, slot VARCHAR(96) NOT NULL, path VARCHAR(255) NOT NULL, sort_order INT NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, INDEX IDX_TEAM_RECAP_GIF_SLOT (slot), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['joker'])) {
            return;
        }

        $jokerColumns = $schemaManager->introspectTable('joker')->getColumns();
        if (!isset($jokerColumns['recap_gif_useful'])) {
            return;
        }

        $this->addSql("INSERT INTO team_recap_gif (slot, path, sort_order, active) SELECT CONCAT('joker.', code, '.useful'), recap_gif_useful, 0, 1 FROM joker WHERE recap_gif_useful IS NOT NULL AND recap_gif_useful <> ''");
        $this->addSql("INSERT INTO team_recap_gif (slot, path, sort_order, active) SELECT CONCAT('joker.', code, '.not_useful'), recap_gif_not_useful, 0, 1 FROM joker WHERE recap_gif_not_useful IS NOT NULL AND recap_gif_not_useful <> ''");
        $this->addSql('ALTER TABLE joker DROP recap_gif_useful, DROP recap_gif_not_useful');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE joker ADD recap_gif_useful VARCHAR(255) DEFAULT NULL, ADD recap_gif_not_useful VARCHAR(255) DEFAULT NULL');
        $this->addSql('DROP TABLE team_recap_gif');
    }
}
