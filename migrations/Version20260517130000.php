<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260517130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Équipe favorite (pays secret) + joker equipe_favorite.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE team ADD favorite_country_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE team ADD CONSTRAINT FK_C4E0A61F9088BDF FOREIGN KEY (favorite_country_id) REFERENCES country (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C4E0A61F9088BDF ON team (favorite_country_id)');

        $this->addSql("INSERT INTO joker (code, name, description, active, sort_order) VALUES (
            'equipe_favorite',
            'Équipe favorite',
            'Choix unique et secret au début de la compétition : une sélection nationale. Votre équipe est protégée des jokers adverses qui vous ciblent sur les matchs de poule où ce pays joue.',
            1,
            9
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM joker WHERE code = 'equipe_favorite'");
        $this->addSql('ALTER TABLE team DROP FOREIGN KEY FK_C4E0A61F9088BDF');
        $this->addSql('DROP INDEX IDX_C4E0A61F9088BDF ON team');
        $this->addSql('ALTER TABLE team DROP favorite_country_id');
    }
}
