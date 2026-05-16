<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Points buteur : base, cote et recalcul des buts existants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE but ADD points_base INT DEFAULT 1 NOT NULL, ADD cote_coefficient DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE but DROP points_base, DROP cote_coefficient');
    }
}
