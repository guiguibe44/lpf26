<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260529100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Buteur : poste (position) et numéro de maillot issus de la synchro squads.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE buteur ADD position VARCHAR(30) DEFAULT NULL, ADD numero INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE buteur DROP position, DROP numero');
    }
}
