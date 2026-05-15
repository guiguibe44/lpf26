<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Horodatage d\'envoi de la relance push pronostic par match.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `match` ADD push_reminder_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `match` DROP push_reminder_sent_at');
    }
}
