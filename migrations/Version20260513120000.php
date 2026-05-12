<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'API-Football : id fixture sur match, id joueur API sur buteur, cle evenement sur but, lieu et arbitre sur match.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `match` ADD api_football_fixture_id INT DEFAULT NULL, ADD venue_name VARCHAR(255) DEFAULT NULL, ADD referee VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_MATCH_API_FOOTBALL_FIXTURE ON `match` (api_football_fixture_id)');
        $this->addSql('ALTER TABLE buteur ADD api_sports_player_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BUTEUR_API_SPORTS_PLAYER ON buteur (api_sports_player_id)');
        $this->addSql('ALTER TABLE but ADD api_sports_event_key VARCHAR(160) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BUT_API_SPORTS_EVENT ON but (api_sports_event_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_BUT_API_SPORTS_EVENT ON but');
        $this->addSql('ALTER TABLE but DROP api_sports_event_key');
        $this->addSql('DROP INDEX UNIQ_BUTEUR_API_SPORTS_PLAYER ON buteur');
        $this->addSql('ALTER TABLE buteur DROP api_sports_player_id');
        $this->addSql('DROP INDEX UNIQ_MATCH_API_FOOTBALL_FIXTURE ON `match`');
        $this->addSql('ALTER TABLE `match` DROP api_football_fixture_id, DROP venue_name, DROP referee');
    }
}
