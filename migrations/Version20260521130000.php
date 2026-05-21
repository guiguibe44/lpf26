<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modèles de phrases joker live (JSON) éditables en admin.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE joker ADD live_story_templates JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE joker DROP live_story_templates');
    }
}
