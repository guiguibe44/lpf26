<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Buteur.pays_id nullable avec ON DELETE SET NULL pour permettre la suppression d\'un pays sans supprimer les buteurs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE buteur DROP FOREIGN KEY FK_83CEC3EEA6E44244');
        $this->addSql('ALTER TABLE buteur CHANGE pays_id pays_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE buteur ADD CONSTRAINT FK_83CEC3EEA6E44244 FOREIGN KEY (pays_id) REFERENCES country (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE buteur DROP FOREIGN KEY FK_83CEC3EEA6E44244');
        $this->addSql('UPDATE buteur SET pays_id = (SELECT MIN(id) FROM country c) WHERE pays_id IS NULL');
        $this->addSql('ALTER TABLE buteur CHANGE pays_id pays_id INT NOT NULL');
        $this->addSql('ALTER TABLE buteur ADD CONSTRAINT FK_83CEC3EEA6E44244 FOREIGN KEY (pays_id) REFERENCES country (id)');
    }
}
