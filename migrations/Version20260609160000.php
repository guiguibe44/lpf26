<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Badge award : colonne seen_at pour notifications de déblocage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE badge_award ADD seen_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('UPDATE badge_award SET seen_at = awarded_at WHERE seen_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE badge_award DROP seen_at');
    }
}
