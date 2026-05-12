<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506154500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les points en cas de mauvais résultat sur les matchs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `match` ADD points_mauvais_resultat INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `match` DROP points_mauvais_resultat');
    }
}
