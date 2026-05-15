<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enrichit le journal des campagnes manuelles (push / e-mail, destinataires).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE push_notification_log ADD send_push TINYINT(1) DEFAULT 1 NOT NULL, ADD send_email TINYINT(1) DEFAULT 0 NOT NULL, ADD recipient_scope VARCHAR(20) DEFAULT \'all\' NOT NULL, ADD players_targeted INT DEFAULT 0 NOT NULL, ADD emails_sent_count INT DEFAULT 0 NOT NULL, ADD emails_failed_count INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE push_notification_log DROP send_push, DROP send_email, DROP recipient_scope, DROP players_targeted, DROP emails_sent_count, DROP emails_failed_count');
    }
}
