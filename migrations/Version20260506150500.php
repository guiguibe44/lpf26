<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506150500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table match (MVP pronostics).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `match` (id INT AUTO_INCREMENT NOT NULL, pays_domicile_id INT NOT NULL, pays_exterieur_id INT NOT NULL, date_heure DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', score_domicile INT DEFAULT NULL, score_exterieur INT DEFAULT NULL, statut VARCHAR(30) NOT NULL, phase VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1A9A7125E4B129A6 (pays_domicile_id), INDEX IDX_1A9A7125C6E8F5A7 (pays_exterieur_id), INDEX IDX_1A9A7125AA31F7CE (date_heure), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `match` ADD CONSTRAINT FK_1A9A7125E4B129A6 FOREIGN KEY (pays_domicile_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE `match` ADD CONSTRAINT FK_1A9A7125C6E8F5A7 FOREIGN KEY (pays_exterieur_id) REFERENCES country (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE `match`');
    }
}
