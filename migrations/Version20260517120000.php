<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Joker 8 collecte de points : catalogue.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO joker (code, name, description, active, sort_order) VALUES (
            'collecte_points',
            'Collecte',
            'Sur un match à venir : après tous les autres jokers, votre équipe prélève 10 % des points équipe de chaque autre équipe sur ce match (arrondi) et les ajoute à son total.',
            1,
            8
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM joker WHERE code = 'collecte_points'");
    }
}
