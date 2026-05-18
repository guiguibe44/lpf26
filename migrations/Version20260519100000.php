<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260519100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Match : synchro API-Football activable par match, minute live, finalisation des scores.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `match` ADD api_football_sync_enabled TINYINT(1) DEFAULT 1 NOT NULL, ADD live_elapsed_minute INT DEFAULT NULL, ADD live_scores_finalized_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD api_football_last_synced_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `match` DROP api_football_sync_enabled, DROP live_elapsed_minute, DROP live_scores_finalized_at, DROP api_football_last_synced_at');
    }
}
