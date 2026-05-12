<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506154000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les points configurables par match (exact et bon résultat).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `match` ADD points_score_exact INT DEFAULT NULL, ADD points_bon_resultat INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `match` DROP points_score_exact, DROP points_bon_resultat');
    }
}
