<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'GIF récap d’équipe (utile / pas utile) par type de joker';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE joker ADD recap_gif_useful VARCHAR(255) DEFAULT NULL, ADD recap_gif_not_useful VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE joker DROP recap_gif_useful, DROP recap_gif_not_useful');
    }
}
