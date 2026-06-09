<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Image optionnelle sur badge_definition.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE badge_definition ADD image VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE badge_definition DROP image');
    }
}
