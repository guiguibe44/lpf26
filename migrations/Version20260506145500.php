<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506145500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime le slug des pays.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_COUNTRY_SLUG ON country');
        $this->addSql('ALTER TABLE country DROP slug');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE country ADD slug VARCHAR(180) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_COUNTRY_SLUG ON country (slug)');
    }
}
