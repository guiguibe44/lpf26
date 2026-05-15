<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Historique des campagnes de notifications push manuelles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE push_notification_log (id INT AUTO_INCREMENT NOT NULL, sent_by_id INT DEFAULT NULL, title VARCHAR(120) NOT NULL, body LONGTEXT NOT NULL, url VARCHAR(512) DEFAULT NULL, target_count INT NOT NULL, sent_count INT NOT NULL, failed_count INT NOT NULL, removed_count INT NOT NULL, sent_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_PUSH_LOG_SENT_BY (sent_by_id), INDEX IDX_PUSH_LOG_SENT_AT (sent_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE push_notification_log ADD CONSTRAINT FK_PUSH_LOG_SENT_BY FOREIGN KEY (sent_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE push_notification_log DROP FOREIGN KEY FK_PUSH_LOG_SENT_BY');
        $this->addSql('DROP TABLE push_notification_log');
    }
}
