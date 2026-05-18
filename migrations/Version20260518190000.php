<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Forum : date de modification des messages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_post ADD updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE forum_post DROP updated_at');
    }
}
