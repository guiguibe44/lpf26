<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Matchs KDO : indicateur is_kdo_match sur la table match.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `match` ADD is_kdo_match TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `match` DROP is_kdo_match');
    }
}
