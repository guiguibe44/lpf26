<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Forum : mentions (@joueur) et notifications in-app.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_notification (
            id INT AUTO_INCREMENT NOT NULL,
            recipient_id INT NOT NULL,
            actor_id INT DEFAULT NULL,
            forum_post_id INT DEFAULT NULL,
            type VARCHAR(32) NOT NULL,
            title VARCHAR(160) NOT NULL,
            body LONGTEXT NOT NULL,
            url VARCHAR(512) DEFAULT NULL,
            read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_USER_NOTIF_RECIPIENT_READ (recipient_id, read_at),
            INDEX IDX_USER_NOTIF_CREATED (created_at),
            INDEX IDX_8A9E609EE92F8F78 (recipient_id),
            INDEX IDX_8A9E609E10DAF24A (actor_id),
            INDEX IDX_8A9E609CE362554 (forum_post_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE forum_post_mention (
            id INT AUTO_INCREMENT NOT NULL,
            forum_post_id INT NOT NULL,
            mentioned_user_id INT NOT NULL,
            INDEX IDX_4B8C49E6C8624F4D (forum_post_id),
            INDEX IDX_4B8C49E6F3ED09E9 (mentioned_user_id),
            UNIQUE INDEX UNIQ_FORUM_POST_MENTION (forum_post_id, mentioned_user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_notification ADD CONSTRAINT FK_8A9E609EE92F8F78 FOREIGN KEY (recipient_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_notification ADD CONSTRAINT FK_8A9E609E10DAF24A FOREIGN KEY (actor_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_notification ADD CONSTRAINT FK_8A9E609CE362554 FOREIGN KEY (forum_post_id) REFERENCES forum_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_post_mention ADD CONSTRAINT FK_4B8C49E6C8624F4D FOREIGN KEY (forum_post_id) REFERENCES forum_post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_post_mention ADD CONSTRAINT FK_4B8C49E6F3ED09E9 FOREIGN KEY (mentioned_user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_notification DROP FOREIGN KEY FK_8A9E609EE92F8F78');
        $this->addSql('ALTER TABLE user_notification DROP FOREIGN KEY FK_8A9E609E10DAF24A');
        $this->addSql('ALTER TABLE user_notification DROP FOREIGN KEY FK_8A9E609CE362554');
        $this->addSql('ALTER TABLE forum_post_mention DROP FOREIGN KEY FK_4B8C49E6C8624F4D');
        $this->addSql('ALTER TABLE forum_post_mention DROP FOREIGN KEY FK_4B8C49E6F3ED09E9');
        $this->addSql('DROP TABLE user_notification');
        $this->addSql('DROP TABLE forum_post_mention');
    }
}
