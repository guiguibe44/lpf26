<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Forum : messages et réponses entre joueurs connectés.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE forum_post (
            id INT AUTO_INCREMENT NOT NULL,
            author_id INT NOT NULL,
            parent_id INT DEFAULT NULL,
            content LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_FORUM_POST_PARENT (parent_id),
            INDEX IDX_FORUM_POST_CREATED (created_at),
            INDEX IDX_269AD2A5F675F31B (author_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE forum_post ADD CONSTRAINT FK_269AD2A5F675F31B FOREIGN KEY (author_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forum_post ADD CONSTRAINT FK_269AD2A5727ACA70 FOREIGN KEY (parent_id) REFERENCES forum_post (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_post DROP FOREIGN KEY FK_269AD2A5F675F31B');
        $this->addSql('ALTER TABLE forum_post DROP FOREIGN KEY FK_269AD2A5727ACA70');
        $this->addSql('DROP TABLE forum_post');
    }
}
