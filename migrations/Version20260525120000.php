<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Barème pronos LPF24 : 30 / 10 / 0 (mise à jour des matchs et contenus existants).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE `match` SET points_score_exact = 30 WHERE points_score_exact = 3');
        $this->addSql('UPDATE `match` SET points_bon_resultat = 10 WHERE points_bon_resultat = 1');
        $this->addSql(<<<'SQL'
            UPDATE site_intro_slide
            SET body = '<p><strong>Score exact</strong> = 30 pts de base, <strong>bon 1/N/2</strong> = 10 pts, sinon 0. Les <strong>cotes</strong> (1, N, 2) montent quand peu de monde parie sur ce résultat — plafond ×5.</p><p>Points finaux = base × cote. Les cotes apparaissent au coup d’envoi.</p>'
            WHERE visual_theme = 'points'
            SQL);

        $this->addSql(<<<'SQL'
            UPDATE joker
            SET technical_explanation = 'À poser sur un match à venir, avant le coup d''envoi.
            Chaque joueur est noté sur sa propre cote (×2 sur le barème du match).
            Score exact : 30 × cote × 2. Bon résultat (sans score exact) : 10 × cote × 2.
            Mauvais résultat : −3 × cote (cote individuelle du pronostic).
            Le total équipe sur le match est la somme des deux joueurs.'
            WHERE code = 'double_equipe'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE `match` SET points_score_exact = 3 WHERE points_score_exact = 30');
        $this->addSql('UPDATE `match` SET points_bon_resultat = 1 WHERE points_bon_resultat = 10');
        $this->addSql(<<<'SQL'
            UPDATE site_intro_slide
            SET body = '<p><strong>Score exact</strong> ≈ 3 pts de base, <strong>bon 1/N/2</strong> ≈ 1 pt, sinon 0. Les <strong>cotes</strong> (1, N, 2) montent quand peu de monde parie sur ce résultat — plafond ×5.</p><p>Points finaux = base × cote. Les cotes apparaissent au coup d’envoi.</p>'
            WHERE visual_theme = 'points'
            SQL);
    }
}
