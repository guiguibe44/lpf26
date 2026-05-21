<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Thèmes de zone principale (main.ta-main) administrables en EasyAdmin.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE main_theme (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(64) NOT NULL,
                label VARCHAR(128) NOT NULL,
                active TINYINT(1) DEFAULT 1 NOT NULL,
                sort_order INT DEFAULT 0 NOT NULL,
                is_default TINYINT(1) DEFAULT 0 NOT NULL,
                background_color VARCHAR(32) DEFAULT NULL,
                background_image VARCHAR(255) DEFAULT NULL,
                background_position VARCHAR(32) DEFAULT 'center center' NOT NULL,
                background_repeat VARCHAR(32) DEFAULT 'no-repeat' NOT NULL,
                title_color VARCHAR(32) NOT NULL,
                block_background_color VARCHAR(32) NOT NULL,
                block_text_color VARCHAR(32) NOT NULL,
                button_background_color VARCHAR(32) NOT NULL,
                button_text_color VARCHAR(32) NOT NULL,
                UNIQUE INDEX UNIQ_MAIN_THEME_CODE (code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO main_theme (
                code, label, active, sort_order, is_default,
                background_color, background_image, background_position, background_repeat,
                title_color, block_background_color, block_text_color,
                button_background_color, button_text_color
            ) VALUES
            (
                'default', 'Défaut', 1, 0, 1,
                'transparent', NULL, 'center center', 'no-repeat',
                '#1b2d4f', '#ffffff', '#0f172a',
                '#16a34a', '#ffffff'
            ),
            (
                'dark', 'Sombre', 1, 10, 0,
                '#17171c', NULL, 'center center', 'no-repeat',
                '#f8fafc', '#ffffff', '#0f172a',
                '#16a34a', '#ffffff'
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE main_theme');
    }
}
