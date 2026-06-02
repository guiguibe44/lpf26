<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602154000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les auteurs fictifs et éditos dashboard planifiables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE editorial_author (id INT AUTO_INCREMENT NOT NULL, first_name VARCHAR(120) NOT NULL, last_name VARCHAR(120) NOT NULL, country VARCHAR(16) NOT NULL, avatar VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE dashboard_editorial (id INT AUTO_INCREMENT NOT NULL, author_id INT NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, published TINYINT(1) NOT NULL, published_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_DASHBOARD_EDITORIAL_AUTHOR (author_id), INDEX IDX_DASHBOARD_EDITORIAL_PUBLISHED_AT (published_at), INDEX IDX_DASHBOARD_EDITORIAL_PUBLISHED (published), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE dashboard_editorial ADD CONSTRAINT FK_DASHBOARD_EDITORIAL_AUTHOR FOREIGN KEY (author_id) REFERENCES editorial_author (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_editorial DROP FOREIGN KEY FK_DASHBOARD_EDITORIAL_AUTHOR');
        $this->addSql('DROP TABLE dashboard_editorial');
        $this->addSql('DROP TABLE editorial_author');
    }
}
