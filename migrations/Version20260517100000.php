<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Joker 6 inversion score : catalogue.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO joker (code, name, description, active, sort_order) VALUES (
            'inverse_score',
            'Inversion score',
            'Sur un match à venir, cible une équipe adverse : ses pronostics sont notés avec le score inversé (ex. 3-0 devient 0-3, 1-1 reste 1-1). La cote du match s''applique sur le score effectif.',
            1,
            6
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM joker WHERE code = 'inverse_score'");
    }
}
