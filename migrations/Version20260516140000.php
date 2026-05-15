<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Historique des relances pronostic (push ou e-mail) par joueur et par match.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE match_reminder_log (
            id INT AUTO_INCREMENT NOT NULL,
            match_id INT DEFAULT NULL,
            user_id INT NOT NULL,
            channel VARCHAR(10) NOT NULL,
            trigger_type VARCHAR(10) NOT NULL,
            title VARCHAR(120) NOT NULL,
            body LONGTEXT NOT NULL,
            url VARCHAR(512) DEFAULT NULL,
            success TINYINT(1) NOT NULL,
            error_message LONGTEXT DEFAULT NULL,
            sent_by_id INT DEFAULT NULL,
            sent_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_MATCH_REMINDER_MATCH (match_id),
            INDEX IDX_MATCH_REMINDER_USER (user_id),
            INDEX IDX_MATCH_REMINDER_SENT_AT (sent_at),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE match_reminder_log ADD CONSTRAINT FK_MATCH_REMINDER_MATCH FOREIGN KEY (match_id) REFERENCES `match` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE match_reminder_log ADD CONSTRAINT FK_MATCH_REMINDER_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE match_reminder_log ADD CONSTRAINT FK_MATCH_REMINDER_SENT_BY FOREIGN KEY (sent_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE match_reminder_log DROP FOREIGN KEY FK_MATCH_REMINDER_MATCH');
        $this->addSql('ALTER TABLE match_reminder_log DROP FOREIGN KEY FK_MATCH_REMINDER_USER');
        $this->addSql('ALTER TABLE match_reminder_log DROP FOREIGN KEY FK_MATCH_REMINDER_SENT_BY');
        $this->addSql('DROP TABLE match_reminder_log');
    }
}
