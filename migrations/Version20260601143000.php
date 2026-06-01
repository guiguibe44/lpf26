<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Data\TeamRecapCopyDefaults;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Textes du récap d’équipe éditables en admin (amorçage des phrases par défaut).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE team_recap_copy (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(64) NOT NULL, category VARCHAR(32) NOT NULL, admin_label VARCHAR(255) NOT NULL, condition_hint LONGTEXT DEFAULT NULL, body LONGTEXT NOT NULL, sort_order INT NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_TEAM_RECAP_COPY_CODE (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        foreach (TeamRecapCopyDefaults::entries() as $entry) {
            $this->addSql(
                'INSERT INTO team_recap_copy (code, category, admin_label, condition_hint, body, sort_order, active) VALUES (:code, :category, :admin_label, :condition_hint, :body, :sort_order, 1)',
                [
                    'code' => $entry['code'],
                    'category' => $entry['category']->value,
                    'admin_label' => $entry['adminLabel'],
                    'condition_hint' => $entry['conditionHint'],
                    'body' => $entry['body'],
                    'sort_order' => $entry['sortOrder'],
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE team_recap_copy');
    }
}
