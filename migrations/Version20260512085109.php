<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260512085109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE but (id INT AUTO_INCREMENT NOT NULL, minute INT DEFAULT NULL, points_attribues INT NOT NULL, created_at DATETIME NOT NULL, buteur_id INT NOT NULL, match_ref_id INT NOT NULL, INDEX IDX_B132FECA59365323 (buteur_id), INDEX IDX_B132FECA80D8B8A8 (match_ref_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE buteur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, photo VARCHAR(255) DEFAULT NULL, pays_id INT NOT NULL, INDEX IDX_83CEC3EEA6E44244 (pays_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE but ADD CONSTRAINT FK_B132FECA59365323 FOREIGN KEY (buteur_id) REFERENCES buteur (id)');
        $this->addSql('ALTER TABLE but ADD CONSTRAINT FK_B132FECA80D8B8A8 FOREIGN KEY (match_ref_id) REFERENCES `match` (id)');
        $this->addSql('ALTER TABLE buteur ADD CONSTRAINT FK_83CEC3EEA6E44244 FOREIGN KEY (pays_id) REFERENCES country (id)');
        $this->addSql('ALTER TABLE user ADD buteur_choisi_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D649809F48D3 FOREIGN KEY (buteur_choisi_id) REFERENCES buteur (id)');
        $this->addSql('CREATE INDEX IDX_8D93D649809F48D3 ON user (buteur_choisi_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE but DROP FOREIGN KEY FK_B132FECA59365323');
        $this->addSql('ALTER TABLE but DROP FOREIGN KEY FK_B132FECA80D8B8A8');
        $this->addSql('ALTER TABLE buteur DROP FOREIGN KEY FK_83CEC3EEA6E44244');
        $this->addSql('DROP TABLE but');
        $this->addSql('DROP TABLE buteur');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_8D93D649809F48D3');
        $this->addSql('DROP INDEX IDX_8D93D649809F48D3 ON `user`');
        $this->addSql('ALTER TABLE `user` DROP buteur_choisi_id');
    }
}
