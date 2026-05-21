<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Voile coloré (opacité) sur l’image de fond des thèmes de page.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE main_theme ADD background_overlay_color VARCHAR(32) DEFAULT NULL, ADD background_overlay_opacity INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE main_theme DROP background_overlay_color, DROP background_overlay_opacity');
    }
}
