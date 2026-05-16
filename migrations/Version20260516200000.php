<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Jokers : catalogue, utilisations par équipe, points équipe sur les pronostics.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE joker (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, sort_order INT DEFAULT 0 NOT NULL, UNIQUE INDEX UNIQ_JOKER_CODE (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE team_joker_usage (id INT AUTO_INCREMENT NOT NULL, team_id INT NOT NULL, joker_id INT NOT NULL, match_id INT NOT NULL, placed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_TEAM_JOKER_USAGE_TEAM (team_id), INDEX IDX_TEAM_JOKER_USAGE_JOKER (joker_id), INDEX IDX_TEAM_JOKER_USAGE_MATCH (match_id), UNIQUE INDEX UNIQ_TEAM_JOKER (team_id, joker_id), UNIQUE INDEX UNIQ_TEAM_MATCH_JOKER (team_id, match_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE team_joker_usage ADD CONSTRAINT FK_TEAM_JOKER_USAGE_TEAM FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_joker_usage ADD CONSTRAINT FK_TEAM_JOKER_USAGE_JOKER FOREIGN KEY (joker_id) REFERENCES joker (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE team_joker_usage ADD CONSTRAINT FK_TEAM_JOKER_USAGE_MATCH FOREIGN KEY (match_id) REFERENCES `match` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pronostic ADD points_equipe DOUBLE PRECISION DEFAULT NULL');

        $this->addSql("INSERT INTO joker (code, name, description, active, sort_order) VALUES (
            'double_equipe',
            'Double équipe',
            'Posé sur un match : double les points équipe des bons pronos (base × cote). Les joueurs affichent 0 pt. Chaque mauvais résultat retire 5 pts à l''équipe (−10 si les deux se trompent).',
            1,
            1
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pronostic DROP points_equipe');
        $this->addSql('ALTER TABLE team_joker_usage DROP FOREIGN KEY FK_TEAM_JOKER_USAGE_TEAM');
        $this->addSql('ALTER TABLE team_joker_usage DROP FOREIGN KEY FK_TEAM_JOKER_USAGE_JOKER');
        $this->addSql('ALTER TABLE team_joker_usage DROP FOREIGN KEY FK_TEAM_JOKER_USAGE_MATCH');
        $this->addSql('DROP TABLE team_joker_usage');
        $this->addSql('DROP TABLE joker');
    }
}
