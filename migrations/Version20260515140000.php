<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Abonnements Web Push (notifications navigateur).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE push_subscription (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, endpoint LONGTEXT NOT NULL, public_key VARCHAR(255) NOT NULL, auth_token VARCHAR(255) NOT NULL, content_encoding VARCHAR(32) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_PUSH_USER (user_id), UNIQUE INDEX UNIQ_PUSH_ENDPOINT (endpoint(500)), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE push_subscription ADD CONSTRAINT FK_PUSH_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE push_subscription DROP FOREIGN KEY FK_PUSH_USER');
        $this->addSql('DROP TABLE push_subscription');
    }
}
