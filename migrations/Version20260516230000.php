<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Joker espion : précise qu\'il est définitif une fois posé.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE joker SET description = 'Sur un match à venir : révèle les cotes du match et tous les jokers déjà posés par les équipes avant le coup d''envoi. Une fois joué, ce joker est définitif et ne peut pas être retiré.' WHERE code = 'espion'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE joker SET description = 'Sur un match à venir : révèle les cotes du match et tous les jokers déjà posés par les équipes avant le coup d''envoi.' WHERE code = 'espion'");
    }
}
