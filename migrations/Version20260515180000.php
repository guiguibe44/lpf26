<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Le pseudo (nickname) doit etre unique par equipe, pas globalement :
 * deux equipes differentes peuvent avoir un joueur "GUIGUI".
 */
final class Version20260515180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remplace l\'unicite globale de team_member.nickname par une contrainte unique (team_id, nickname).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_6FFBDA1A188FE64 ON team_member');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_team_member_team_id_nickname ON team_member (team_id, nickname)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_team_member_team_id_nickname ON team_member');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6FFBDA1A188FE64 ON team_member (nickname)');
    }
}
