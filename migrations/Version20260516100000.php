<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la lettre de groupe (A–L) sur les pays pour l’édition EasyAdmin.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE country ADD groupe VARCHAR(1) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_COUNTRY_GROUPE ON country (groupe)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_COUNTRY_GROUPE ON country');
        $this->addSql('ALTER TABLE country DROP groupe');
    }
}
