<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506203500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les prises de risque sur les pronostics et l historique de classement des equipes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pronostic ADD prise_risque TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('CREATE TABLE team_ranking_snapshot (id INT AUTO_INCREMENT NOT NULL, match_ref_id INT NOT NULL, team_id INT NOT NULL, position INT NOT NULL, total_points DOUBLE PRECISION NOT NULL, scores_exacts INT NOT NULL, bons_resultats INT NOT NULL, prises_risque INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2F081A44AC5153C8 (match_ref_id), INDEX IDX_2F081A44296CD8AE (team_id), UNIQUE INDEX UNIQ_TEAM_MATCH_SNAPSHOT (match_ref_id, team_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE team_ranking_snapshot ADD CONSTRAINT FK_2F081A44AC5153C8 FOREIGN KEY (match_ref_id) REFERENCES `match` (id)');
        $this->addSql('ALTER TABLE team_ranking_snapshot ADD CONSTRAINT FK_2F081A44296CD8AE FOREIGN KEY (team_id) REFERENCES team (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team_ranking_snapshot DROP FOREIGN KEY FK_2F081A44AC5153C8');
        $this->addSql('ALTER TABLE team_ranking_snapshot DROP FOREIGN KEY FK_2F081A44296CD8AE');
        $this->addSql('DROP TABLE team_ranking_snapshot');
        $this->addSql('ALTER TABLE pronostic DROP prise_risque');
    }
}
