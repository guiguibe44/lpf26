<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516240000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Joker 4 double buteur : catalogue.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO joker (code, name, description, active, sort_order) VALUES (
            'double_buteur',
            'Double buteur',
            'Sur un match à venir impliquant le pays d''un des buteurs de votre équipe : les points buteur marqués sur ce match comptent double pour votre équipe.',
            1,
            4
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM joker WHERE code = 'double_buteur'");
    }
}
