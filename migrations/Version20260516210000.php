<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Joker 2 pique de points : cible équipe + catalogue.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_joker_usage ADD target_team_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE team_joker_usage ADD CONSTRAINT FK_TEAM_JOKER_USAGE_TARGET FOREIGN KEY (target_team_id) REFERENCES team (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_TEAM_JOKER_USAGE_TARGET ON team_joker_usage (target_team_id)');

        $this->addSql("INSERT INTO joker (code, name, description, active, sort_order) VALUES (
            'pique_points',
            'Pique de points',
            'Sur un match à venir, vise une équipe adverse : ses points du match passent à 0 et vous récupérez les vôtres plus les siens. Si les deux équipes se ciblent mutuellement, les totaux du match sont inversés.',
            1,
            2
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM joker WHERE code = 'pique_points'");
        $this->addSql('ALTER TABLE team_joker_usage DROP FOREIGN KEY FK_TEAM_JOKER_USAGE_TARGET');
        $this->addSql('DROP INDEX IDX_TEAM_JOKER_USAGE_TARGET ON team_joker_usage');
        $this->addSql('ALTER TABLE team_joker_usage DROP target_team_id');
    }
}
