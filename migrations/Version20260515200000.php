<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Encodage push par défaut aes128gcm pour les abonnements existants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE push_subscription SET content_encoding = 'aes128gcm' WHERE content_encoding IS NULL OR content_encoding = ''");
    }

    public function down(Schema $schema): void
    {
        // pas de retour arrière fiable
    }
}
