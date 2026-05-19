<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260520120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notes partagées entre administrateurs sur la checklist QA.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_qa_checklist_note (id INT AUTO_INCREMENT NOT NULL, item_id VARCHAR(120) NOT NULL, content LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', user_id INT NOT NULL, INDEX IDX_QA_NOTE_ITEM (item_id), INDEX IDX_QA_NOTE_USER (user_id), UNIQUE INDEX UNIQ_QA_NOTE_ITEM_USER (item_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE admin_qa_checklist_note ADD CONSTRAINT FK_QA_NOTE_USER FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_qa_checklist_note DROP FOREIGN KEY FK_QA_NOTE_USER');
        $this->addSql('DROP TABLE admin_qa_checklist_note');
    }
}
