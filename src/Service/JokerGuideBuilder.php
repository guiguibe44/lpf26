<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Joker;
use App\Repository\JokerRepository;

/**
 * Contenu du guide public des jokers (règles communes + détails par type).
 */
final class JokerGuideBuilder
{
    public function __construct(
        private readonly JokerRepository $jokerRepository,
    ) {
    }

    /**
     * @return list<array{
     *     joker: Joker,
     *     category: string,
     *     category_label: string,
     *     details: list<string>,
     *     irreversible: bool,
     *     targets_opponent: bool
     * }>
     */
    public function buildCatalog(): array
    {
        $entries = [];

        foreach ($this->jokerRepository->findAllOrdered() as $joker) {
            if (!$joker->isActive()) {
                continue;
            }

            $code = (string) $joker->getCode();
            $meta = self::metaForCode($code);

            $entries[] = [
                'joker' => $joker,
                'category' => $meta['category'],
                'category_label' => $meta['category_label'],
                'details' => $meta['details'],
                'irreversible' => $meta['irreversible'],
                'targets_opponent' => $meta['targets_opponent'],
            ];
        }

        return $entries;
    }

    /**
     * @return array{
     *     category: string,
     *     category_label: string,
     *     details: list<string>,
     *     irreversible: bool,
     *     targets_opponent: bool
     * }
     */
    private static function metaForCode(string $code): array
    {
        return match ($code) {
            Joker::CODE_DOUBLE_EQUIPE => [
                'category' => 'points',
                'category_label' => 'Points équipe',
                'irreversible' => false,
                'targets_opponent' => false,
                'details' => [
                    'À poser sur un match à venir, avant le coup d\'envoi.',
                    'Chaque joueur est noté sur sa propre cote (×2 sur le barème du match).',
                    'Score exact : 3 × cote × 2. Bon résultat (sans score exact) : 1 × cote × 2.',
                    'Mauvais résultat : −3 × cote (cote individuelle du pronostic).',
                    'Le total équipe sur le match est la somme des deux joueurs.',
                ],
            ],
            Joker::CODE_PIQUE_POINTS => [
                'category' => 'offensive',
                'category_label' => 'Offensif',
                'irreversible' => false,
                'targets_opponent' => true,
                'details' => [
                    'Cible une équipe adverse sur le match choisi.',
                    'Ses points équipe du match passent à 0 ; vous récupérez les vôtres plus les siens.',
                    'Si deux équipes se ciblent mutuellement sur le même match, les totaux équipe du match sont inversés.',
                    'Sans effet si la cible est protégée (bouclier ou équipe favorite en poule).',
                ],
            ],
            Joker::CODE_ESPION => [
                'category' => 'intel',
                'category_label' => 'Renseignement',
                'irreversible' => true,
                'targets_opponent' => false,
                'details' => [
                    'Révèle les cotes estimées du match et la liste des jokers déjà posés par les équipes.',
                    'Une fois joué, ce joker est définitif : il ne peut plus être retiré.',
                ],
            ],
            Joker::CODE_DOUBLE_BUTEUR => [
                'category' => 'buteur',
                'category_label' => 'Buteurs',
                'irreversible' => false,
                'targets_opponent' => false,
                'details' => [
                    'Uniquement sur un match où joue le pays d\'un de vos buteurs.',
                    'Les points buteur de votre équipe sur ce match sont doublés.',
                ],
            ],
            Joker::CODE_INVERSE_BUTEUR => [
                'category' => 'offensive',
                'category_label' => 'Offensif',
                'irreversible' => false,
                'targets_opponent' => true,
                'details' => [
                    'Cible une équipe adverse dont un buteur a un pays qui joue ce match.',
                    'Les points buteur de la cible sur ce match deviennent négatifs.',
                    'Sans effet si la cible est protégée (bouclier ou équipe favorite en poule).',
                ],
            ],
            Joker::CODE_INVERSE_SCORE => [
                'category' => 'offensive',
                'category_label' => 'Offensif',
                'irreversible' => false,
                'targets_opponent' => true,
                'details' => [
                    'Cible une équipe adverse sur le match choisi.',
                    'Les pronostics de la cible sont notés comme si le score réel était inversé (ex. 2-1 lu 1-2).',
                    'Sans effet si la cible est protégée (bouclier ou équipe favorite en poule).',
                ],
            ],
            Joker::CODE_BOUCLIER => [
                'category' => 'defense',
                'category_label' => 'Défense',
                'irreversible' => false,
                'targets_opponent' => false,
                'details' => [
                    'Posé sur un match : protège votre équipe pour toute la journée calendaire de ce match.',
                    'Les jokers adverses qui vous ciblent (pique, inversion buteur, inversion score) sont consommés sans effet.',
                    'Visible des adversaires lorsqu\'ils choisissent une cible.',
                ],
            ],
            Joker::CODE_COLLECTE_POINTS => [
                'category' => 'points',
                'category_label' => 'Points équipe',
                'irreversible' => false,
                'targets_opponent' => false,
                'details' => [
                    'Après application de tous les autres jokers sur le match.',
                    'Votre équipe prélève 10 % des points équipe de chaque autre équipe sur ce match (arrondi à l\'entier).',
                    'Ne cible pas une équipe en particulier.',
                ],
            ],
            Joker::CODE_EQUIPE_FAVORITE => [
                'category' => 'defense',
                'category_label' => 'Défense',
                'irreversible' => false,
                'targets_opponent' => false,
                'details' => [
                    'Choix unique d\'une sélection nationale, secret pour les autres équipes.',
                    'Protection sur les matchs de poule où ce pays joue (domicile ou extérieur).',
                    'Même neutralisation que le bouclier pour les jokers offensifs qui vous ciblent.',
                    'Le choix n\'est pas visible des adversaires dans la liste des cibles.',
                ],
            ],
            default => [
                'category' => 'other',
                'category_label' => 'Joker',
                'irreversible' => false,
                'targets_opponent' => false,
                'details' => [],
            ],
        };
    }
}
