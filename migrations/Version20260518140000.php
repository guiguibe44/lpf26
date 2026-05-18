<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260518140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Jokers : titre, tag, explications techniques (contenu éditorial en base).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE joker ADD title VARCHAR(255) DEFAULT NULL, ADD tag VARCHAR(32) DEFAULT NULL, ADD technical_explanation LONGTEXT DEFAULT NULL');
        $this->addSql('UPDATE joker SET title = name WHERE title IS NULL OR title = \'\'');
        $this->addSql('ALTER TABLE joker MODIFY title VARCHAR(255) NOT NULL');

        foreach ($this->seedRows() as $code => $row) {
            $this->addSql(
                'UPDATE joker SET tag = :tag, technical_explanation = :tech WHERE code = :code',
                [
                    'tag' => $row['tag'],
                    'tech' => $row['technical'],
                    'code' => $code,
                ],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE joker DROP title, DROP tag, DROP technical_explanation');
    }

    /**
     * @return array<string, array{tag: string, technical: string}>
     */
    private function seedRows(): array
    {
        return [
            'double_equipe' => [
                'tag' => 'points',
                'technical' => <<<'TXT'
À poser sur un match à venir, avant le coup d'envoi.
Chaque joueur est noté sur sa propre cote (×2 sur le barème du match).
Score exact : 3 × cote × 2. Bon résultat (sans score exact) : 1 × cote × 2.
Mauvais résultat : −3 × cote (cote individuelle du pronostic).
Le total équipe sur le match est la somme des deux joueurs.
TXT,
            ],
            'pique_points' => [
                'tag' => 'attaque',
                'technical' => <<<'TXT'
Cible une équipe adverse sur le match choisi.
Ses points équipe du match passent à 0 ; vous récupérez les vôtres plus les siens.
Si deux équipes se ciblent mutuellement sur le même match, les totaux équipe du match sont inversés.
Sans effet si la cible est protégée (bouclier ou équipe favorite en poule).
TXT,
            ],
            'espion' => [
                'tag' => 'intel',
                'technical' => <<<'TXT'
Révèle les cotes estimées du match et la liste des jokers déjà posés par les équipes.
Une fois joué, ce joker est définitif : il ne peut plus être retiré.
TXT,
            ],
            'double_buteur' => [
                'tag' => 'buteur',
                'technical' => <<<'TXT'
Uniquement sur un match où joue le pays d'un de vos buteurs.
Les points buteur de votre équipe sur ce match sont doublés.
TXT,
            ],
            'inverse_buteur' => [
                'tag' => 'attaque',
                'technical' => <<<'TXT'
Cible une équipe adverse dont un buteur a un pays qui joue ce match.
Les points buteur de la cible sur ce match deviennent négatifs.
Sans effet si la cible est protégée (bouclier ou équipe favorite en poule).
TXT,
            ],
            'inverse_score' => [
                'tag' => 'attaque',
                'technical' => <<<'TXT'
Cible une équipe adverse sur le match choisi.
Les pronostics de la cible sont notés comme si le score réel était inversé (ex. 2-1 lu 1-2).
Sans effet si la cible est protégée (bouclier ou équipe favorite en poule).
TXT,
            ],
            'bouclier' => [
                'tag' => 'defense',
                'technical' => <<<'TXT'
Posé sur un match : protège votre équipe pour toute la journée calendaire de ce match.
Les jokers adverses qui vous ciblent (pique, inversion buteur, inversion score) sont consommés sans effet.
Visible des adversaires lorsqu'ils choisissent une cible.
TXT,
            ],
            'collecte_points' => [
                'tag' => 'bonus',
                'technical' => <<<'TXT'
Après application de tous les autres jokers sur le match.
Votre équipe prélève 10 % des points équipe de chaque autre équipe sur ce match (arrondi à l'entier).
Ne cible pas une équipe en particulier.
TXT,
            ],
            'equipe_favorite' => [
                'tag' => 'defense',
                'technical' => <<<'TXT'
Choix unique d'une sélection nationale, secret pour les autres équipes.
Protection sur les matchs de poule où ce pays joue (domicile ou extérieur).
Même neutralisation que le bouclier pour les jokers offensifs qui vous ciblent.
Le choix n'est pas visible des adversaires dans la liste des cibles.
TXT,
            ],
        ];
    }
}
