<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260521100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cotes 1/N/2 sur match : cote_domicile, cote_nul, cote_exterieur.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `match` ADD cote_domicile DOUBLE PRECISION DEFAULT NULL, ADD cote_nul DOUBLE PRECISION DEFAULT NULL, ADD cote_exterieur DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `match` DROP cote_domicile, DROP cote_nul, DROP cote_exterieur');
    }
}
