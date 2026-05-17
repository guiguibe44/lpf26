<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516250000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Joker 5 inversion buteur : catalogue.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO joker (code, name, description, active, sort_order) VALUES (
            'inverse_buteur',
            'Inversion buteur',
            'Sur un match à venir impliquant le pays d''un buteur de l''équipe ciblée : les points buteur qu''elle marquerait sur ce match (cote incluse) sont retirés de son classement au lieu d''être ajoutés.',
            1,
            5
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM joker WHERE code = 'inverse_buteur'");
    }
}
