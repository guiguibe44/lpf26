<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506144000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table des pays participants (nom, slug, drapeau).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE country (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, slug VARCHAR(180) NOT NULL, drapeau VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_COUNTRY_SLUG (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE country');
    }
}
