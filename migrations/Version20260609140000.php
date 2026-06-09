<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Data\BadgeCatalogSeed;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260609140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalogue badges (définitions + attributions) et seed shortlist v1.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE badge_definition (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                category VARCHAR(32) NOT NULL,
                scope VARCHAR(16) NOT NULL,
                outcome VARCHAR(16) DEFAULT NULL,
                criterion_hint LONGTEXT DEFAULT NULL,
                flavor_text LONGTEXT DEFAULT NULL,
                icon VARCHAR(64) DEFAULT NULL,
                ironic TINYINT(1) DEFAULT 0 NOT NULL,
                active TINYINT(1) DEFAULT 1 NOT NULL,
                sort_order INT NOT NULL,
                UNIQUE INDEX UNIQ_BADGE_DEFINITION_CODE (code),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE badge_award (
                id INT AUTO_INCREMENT NOT NULL,
                badge_definition_id INT NOT NULL,
                user_id INT DEFAULT NULL,
                team_id INT DEFAULT NULL,
                awarded_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                metadata JSON DEFAULT NULL,
                INDEX IDX_BADGE_AWARD_DEFINITION (badge_definition_id),
                INDEX IDX_BADGE_AWARD_USER (user_id),
                INDEX IDX_BADGE_AWARD_TEAM (team_id),
                PRIMARY KEY (id),
                CONSTRAINT FK_BADGE_AWARD_DEFINITION FOREIGN KEY (badge_definition_id) REFERENCES badge_definition (id) ON DELETE CASCADE,
                CONSTRAINT FK_BADGE_AWARD_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
                CONSTRAINT FK_BADGE_AWARD_TEAM FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
    }

    public function postUp(Schema $schema): void
    {
        $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM badge_definition');
        if ($count > 0) {
            return;
        }

        foreach (BadgeCatalogSeed::definitions() as $row) {
            $this->connection->insert('badge_definition', [
                'code' => $row['code'],
                'name' => $row['name'],
                'category' => $row['category']->value,
                'scope' => $row['scope']->value,
                'outcome' => $row['outcome']?->value,
                'criterion_hint' => $row['criterionHint'],
                'flavor_text' => $row['flavorText'],
                'icon' => $row['icon'],
                'ironic' => $row['ironic'] ? 1 : 0,
                'active' => 1,
                'sort_order' => $row['sortOrder'],
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE badge_award');
        $this->addSql('DROP TABLE badge_definition');
    }
}
