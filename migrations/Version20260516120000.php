<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260516120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalise drapeau/photo : nom de fichier seul (affichage EasyAdmin).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE country SET drapeau = SUBSTRING(drapeau, 19) WHERE drapeau LIKE '/uploads/drapeaux/%'");
        $this->addSql("UPDATE buteur SET photo = SUBSTRING(photo, 18) WHERE photo LIKE '/uploads/buteurs/%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE country SET drapeau = CONCAT('/uploads/drapeaux/', drapeau) WHERE drapeau IS NOT NULL AND drapeau NOT LIKE 'http%' AND drapeau NOT LIKE '/uploads/%'");
        $this->addSql("UPDATE buteur SET photo = CONCAT('/uploads/buteurs/', photo) WHERE photo IS NOT NULL AND photo NOT LIKE 'http%' AND photo NOT LIKE '/uploads/%'");
    }
}
