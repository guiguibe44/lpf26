<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Joker 3 espion : catalogue.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO joker (code, name, description, active, sort_order) VALUES (
            'espion',
            'Espion',
            'Posé sur un match à venir : révèle les cotes du match et tous les jokers déjà posés par les équipes avant le coup d''envoi.',
            1,
            3
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM joker WHERE code = 'espion'");
    }
}
