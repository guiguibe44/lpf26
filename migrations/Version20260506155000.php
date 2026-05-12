<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506155000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les champs de cote et points base sur pronostic, et passe points en décimal.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pronostic ADD points_base INT DEFAULT NULL, ADD cote_coefficient DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE pronostic CHANGE points points DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pronostic DROP points_base, DROP cote_coefficient');
        $this->addSql('ALTER TABLE pronostic CHANGE points points INT DEFAULT NULL');
    }
}
