<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506151500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table pronostic pour les scores pronostiqués par joueur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pronostic (id INT AUTO_INCREMENT NOT NULL, joueur_id INT NOT NULL, `match_id` INT NOT NULL, score_domicile INT NOT NULL, score_exterieur INT NOT NULL, points INT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1148E4E7A76ED395 (joueur_id), INDEX IDX_1148E4E76B6BD148 (match_id), UNIQUE INDEX UNIQ_PRONOSTIC_USER_MATCH (joueur_id, match_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pronostic ADD CONSTRAINT FK_1148E4E7A76ED395 FOREIGN KEY (joueur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE pronostic ADD CONSTRAINT FK_1148E4E76B6BD148 FOREIGN KEY (match_id) REFERENCES `match` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pronostic');
    }
}
