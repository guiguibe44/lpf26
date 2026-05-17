<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Joker 7 bouclier : catalogue.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO joker (code, name, description, active, sort_order) VALUES (
            'bouclier',
            'Bouclier',
            'Sur un match de la journée : votre équipe est protégée toute la journée. Les jokers adverses qui vous ciblent (pique, inversion buteur, inversion score) sont consommés sans effet sur vous.',
            1,
            7
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM joker WHERE code = 'bouclier'");
    }
}
