<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Journal des envois du récap d’équipe par e-mail (tous les 2 jours).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE recap_email_batch (id INT AUTO_INCREMENT NOT NULL, period_start DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', period_end DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', emails_sent INT NOT NULL, teams_notified INT NOT NULL, matches_in_period INT NOT NULL, dry_run TINYINT(1) DEFAULT 0 NOT NULL, sent_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_RECAP_EMAIL_BATCH_SENT_AT (sent_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE recap_email_batch');
    }
}
