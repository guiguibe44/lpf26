<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Joker;
use App\Repository\JokerRepository;

/**
 * Contenu éditorial du guide « interactions » (/jokers), aligné sur les règles implémentées en code.
 */
final class JokerInteractionsGuideBuilder
{
    /**
     * @var array<string, array{title: string, image: ?string, icon: string}>
     */
    private array $jokerMetaByCode = [];

    public function __construct(
        private readonly JokerRepository $jokerRepository,
    ) {
    }

    /**
     * @return array{
     *     toc: list<array{id: string, label: string, group: string}>,
     *     pipeline: array{title: string, intro: string, table: array{headers: list<string>, rows: list<list<string>>}},
     *     jokers: list<array{
     *         code: string,
     *         anchor: string,
     *         title: string,
     *         image: ?string,
     *         icon_class: string,
     *         intro: string,
     *         tables: list<array{caption: ?string, headers: list<string>, rows: list<list<string>>}>,
     *         notes: list<string>
     *     }>,
     *     cross_matrix: array{title: string, intro: string, table: array{headers: list<string>, rows: list<list<string>>}}
     * }
     */
    public function build(): array
    {
        $this->jokerMetaByCode = $this->loadJokerMetaByCode();

        return [
            'toc' => $this->buildToc(),
            'pipeline' => $this->buildPipeline(),
            'jokers' => $this->buildJokerSections(),
            'cross_matrix' => $this->buildCrossMatrix(),
        ];
    }

    /**
     * @return list<array{id: string, label: string, group: string}>
     */
    private function buildToc(): array
    {
        $toc = [
            ['id' => 'guide-pipeline', 'label' => 'Ordre de calcul (pronos)', 'group' => 'interactions'],
            ['id' => 'guide-cross-matrix', 'label' => 'Tableau croisé', 'group' => 'interactions'],
        ];

        foreach ($this->jokerSectionOrder() as $code) {
            $toc[] = [
                'id' => $this->anchorForCode($code),
                'label' => $this->t($code),
                'group' => 'detail',
            ];
        }

        return $toc;
    }

    /**
     * @return array<string, array{title: string, image: ?string, icon: string}>
     */
    private function loadJokerMetaByCode(): array
    {
        $meta = [];
        foreach ($this->jokerRepository->findAllOrdered() as $joker) {
            $code = $joker->getCode();
            if (null === $code || '' === $code) {
                continue;
            }

            $image = $joker->getImage();
            $meta[$code] = [
                'title' => $joker->getDisplayTitle(),
                'image' => (null !== $image && '' !== trim($image)) ? $image : null,
                'icon' => Joker::tablerIconClassForCode($code),
            ];
        }

        return $meta;
    }

    private function t(string $code): string
    {
        $title = $this->jokerMetaByCode[$code]['title'] ?? null;
        if (null !== $title && '' !== trim($title)) {
            return $title;
        }

        return $this->fallbackTitle($code);
    }

    private function imageForCode(string $code): ?string
    {
        return $this->jokerMetaByCode[$code]['image'] ?? null;
    }

    private function iconClassForCode(string $code): string
    {
        return $this->jokerMetaByCode[$code]['icon'] ?? Joker::tablerIconClassForCode($code);
    }

    private function protectionPairLabel(): string
    {
        return sprintf(
            '%s ou %s',
            $this->t(Joker::CODE_BOUCLIER),
            $this->t(Joker::CODE_EQUIPE_FAVORITE),
        );
    }

    /**
     * @return list<string>
     */
    private function jokerSectionOrder(): array
    {
        return [
            Joker::CODE_DOUBLE_EQUIPE,
            Joker::CODE_PIQUE_POINTS,
            Joker::CODE_COLLECTE_POINTS,
            Joker::CODE_INVERSE_SCORE,
            Joker::CODE_DOUBLE_BUTEUR,
            Joker::CODE_INVERSE_BUTEUR,
            Joker::CODE_BOUCLIER,
            Joker::CODE_EQUIPE_FAVORITE,
            Joker::CODE_ESPION,
        ];
    }

    private function anchorForCode(string $code): string
    {
        return 'guide-joker-'.str_replace('_', '-', $code);
    }

    private function fallbackTitle(string $code): string
    {
        return match ($code) {
            Joker::CODE_DOUBLE_EQUIPE => 'Double équipe',
            Joker::CODE_PIQUE_POINTS => 'Pique de points',
            Joker::CODE_COLLECTE_POINTS => 'Collecte de points',
            Joker::CODE_INVERSE_SCORE => 'Inversion du score',
            Joker::CODE_DOUBLE_BUTEUR => 'Double buteur',
            Joker::CODE_INVERSE_BUTEUR => 'Inversion buteur',
            Joker::CODE_BOUCLIER => 'Bouclier',
            Joker::CODE_EQUIPE_FAVORITE => 'Équipe favorite',
            Joker::CODE_ESPION => 'Espion',
            default => $code,
        };
    }

    /**
     * @return array{title: string, intro: string, table: array{headers: list<string>, rows: list<list<string>>}}
     */
    private function buildPipeline(): array
    {
        $invScore = $this->t(Joker::CODE_INVERSE_SCORE);
        $doubleEquipe = $this->t(Joker::CODE_DOUBLE_EQUIPE);
        $pique = $this->t(Joker::CODE_PIQUE_POINTS);
        $collecte = $this->t(Joker::CODE_COLLECTE_POINTS);

        return [
            'title' => 'Ordre de calcul des points prono sur un match',
            'intro' => 'Sur un match terminé, les points affichés au classement équipe passent par les étapes suivantes (les buteurs suivent un calcul séparé, voir plus bas).',
            'table' => [
                'headers' => ['Étape', 'Ce qui change', 'Exemple (2 joueurs, 10 pts chacun avant jokers)'],
                'rows' => [
                    [
                        '1. Score effectif',
                        sprintf('Inversion du score saisi pour l’équipe ciblée par « %s » (si non protégée).', $invScore),
                        'Sans inversion : total brut équipe = 20 pts.',
                    ],
                    [
                        '2. Barème × cote',
                        'Points joueur = base du barème × cote 1 / N / 2 (plafond ×5, arrondi 0,5). '
                        .'Bon prono ou score exact : cote du résultat réel ; mauvais prono : cote de votre issue.',
                        '10 + 10 = 20 pts équipe (contribution prono).',
                    ],
                    [
                        '3. '.$doubleEquipe,
                        'Si votre équipe a posé ce joker : bon prono ×2 ; mauvais prono −3×cote (joueur et équipe).',
                        'Bon prono : 20 → 40 pts équipe.',
                    ],
                    [
                        '4. '.$pique,
                        sprintf('Transfert du total prono de la cible vers l’équipe qui pose « %s » (échange si ciblage mutuel).', $pique),
                        'Vous avez 40, on vous pique : vous 0, adversaire +40.',
                    ],
                    [
                        '5. '.$collecte,
                        sprintf('Chaque autre équipe perd 10 %% (arrondi) de son total prono ; la somme est ajoutée à l’équipe qui pose « %s ».', $collecte),
                        'Vous avez 40, collecte adverse : vous 36 (−4), collecteur +4.',
                    ],
                    [
                        '6. Répartition',
                        'Le total équipe final est réparti entre les joueurs (champ points équipe).',
                        '40 pts répartis au prorata des contributions.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<array{
     *     code: string,
     *     anchor: string,
     *     title: string,
     *     intro: string,
     *     tables: list<array{caption: ?string, headers: list<string>, rows: list<list<string>>}>,
     *     notes: list<string>
     * }>
     */
    private function buildJokerSections(): array
    {
        $sections = [];

        foreach ($this->jokerSectionOrder() as $code) {
            $section = match ($code) {
                Joker::CODE_DOUBLE_EQUIPE => $this->sectionDoubleEquipe(),
                Joker::CODE_PIQUE_POINTS => $this->sectionPiquePoints(),
                Joker::CODE_COLLECTE_POINTS => $this->sectionCollectePoints(),
                Joker::CODE_INVERSE_SCORE => $this->sectionInverseScore(),
                Joker::CODE_DOUBLE_BUTEUR => $this->sectionDoubleButeur(),
                Joker::CODE_INVERSE_BUTEUR => $this->sectionInverseButeur(),
                Joker::CODE_BOUCLIER => $this->sectionBouclier(),
                Joker::CODE_EQUIPE_FAVORITE => $this->sectionEquipeFavorite(),
                Joker::CODE_ESPION => $this->sectionEspion(),
                default => [
                    'code' => $code,
                    'anchor' => $this->anchorForCode($code),
                    'title' => $this->t($code),
                    'intro' => '',
                    'tables' => [],
                    'notes' => [],
                ],
            };

            $section['image'] = $this->imageForCode($code);
            $section['icon_class'] = $this->iconClassForCode($code);
            $sections[] = $section;
        }

        return $sections;
    }

    /**
     * @return array{code: string, anchor: string, title: string, intro: string, tables: list<array{caption: ?string, headers: list<string>, rows: list<list<string>>}>, notes: list<string>}
     */
    private function sectionDoubleEquipe(): array
    {
        $mult = (string) JokerScoringApplicator::DOUBLE_EQUIPE_WRONG_PENALTY_MULTIPLIER;

        return [
            'code' => Joker::CODE_DOUBLE_EQUIPE,
            'anchor' => $this->anchorForCode(Joker::CODE_DOUBLE_EQUIPE),
            'title' => $this->t(Joker::CODE_DOUBLE_EQUIPE),
            'intro' => 'Modifie les points prono de votre équipe sur le match où le joker est posé. Un seul usage par équipe sur toute la compétition.',
            'tables' => [
                [
                    'caption' => 'Formules (par joueur, après barème × cote)',
                    'headers' => ['Cas', 'Calcul', 'Exemple (base 3 pts, cote 2 → 6 pts standard)'],
                    'rows' => [
                        ['Bon prono (pas « mauvais résultat »)', '2 × base × cote', '2 × 3 × 2 = 12 pts joueur et équipe'],
                        ['Mauvais prono', '−'.$mult.' × cote (arrondi)', '−'.$mult.' × 2 = −6 pts joueur et équipe'],
                    ],
                ],
                [
                    'caption' => 'Avec d’autres jokers sur le même match',
                    'headers' => ['Autre joker', 'Interaction', 'Exemple chiffré'],
                    'rows' => [
                        [
                            $this->t(Joker::CODE_PIQUE_POINTS).' (vous êtes la cible)',
                            'Le voleur récupère votre total déjà doublé.',
                            'Vous 40 pts → adversaire +40, vous 0.',
                        ],
                        [
                            $this->t(Joker::CODE_COLLECTE_POINTS).' (autre équipe)',
                            'La taxe de 10 % s’applique sur votre total après double.',
                            'Vous 40 pts → vous 36 si un collecteur prélève 4.',
                        ],
                        [
                            $this->t(Joker::CODE_INVERSE_SCORE).' (sur vous)',
                            'Le double s’applique après le score effectif inversé.',
                            'Score noté différent → base différente → puis ×2 ou malus.',
                        ],
                    ],
                ],
            ],
            'notes' => [
                'Ne modifie pas les points buteur.',
                'Les points « joueur » et la contribution « équipe » sont identiques tant que le vol / la collecte n’ont pas encore redistribué les points équipe.',
            ],
        ];
    }

    /**
     * @return array{code: string, anchor: string, title: string, intro: string, tables: list<array{caption: ?string, headers: list<string>, rows: list<list<string>>}>, notes: list<string>}
     */
    private function sectionPiquePoints(): array
    {
        return [
            'code' => Joker::CODE_PIQUE_POINTS,
            'anchor' => $this->anchorForCode(Joker::CODE_PIQUE_POINTS),
            'title' => $this->t(Joker::CODE_PIQUE_POINTS),
            'intro' => sprintf(
                'Joker offensif : vous choisissez une équipe adverse. Après calcul des pronos (dont « %s » éventuel), son total prono sur ce match est transféré vers votre équipe.',
                $this->t(Joker::CODE_DOUBLE_EQUIPE),
            ),
            'tables' => [
                [
                    'caption' => 'Règle de transfert',
                    'headers' => ['Situation', 'Effet sur les totaux prono du match', 'Exemple'],
                    'rows' => [
                        [
                            'Pique simple (A pique B)',
                            'Total B ajouté à A ; B tombe à 0.',
                            'B avait 25 → A +25, B 0.',
                        ],
                        [
                            'Pique mutuel (A pique B et B pique A)',
                            'Échange des totaux (pas d’addition).',
                            'A avait 30, B avait 12 → A 12, B 30.',
                        ],
                        [
                            'Cible protégée ('.$this->protectionPairLabel().' sur ce match)',
                            'Aucun transfert ; le joker est consommé sans effet.',
                            'B reste 25, A garde son total.',
                        ],
                    ],
                ],
                [
                    'caption' => 'Ordre par rapport à la '.$this->t(Joker::CODE_COLLECTE_POINTS),
                    'headers' => ['Étape', 'Effet'],
                    'rows' => [
                        [$this->t(Joker::CODE_PIQUE_POINTS).' d’abord', 'Les totaux volés ou à zéro servent de base à la collecte.'],
                        [$this->t(Joker::CODE_COLLECTE_POINTS).' ensuite', '10 % prélevé sur chaque autre équipe (hors collecteur), y compris sur un total déjà vidé par le pique.'],
                    ],
                ],
            ],
            'notes' => [
                'Ne vole pas les points buteur, uniquement la contribution prono (points équipe / points joueur).',
                sprintf(
                    'Si la cible a un total négatif (ex. %s raté), ce total négatif est transféré tel quel.',
                    $this->t(Joker::CODE_DOUBLE_EQUIPE),
                ),
            ],
        ];
    }

    /**
     * @return array{code: string, anchor: string, title: string, intro: string, tables: list<array{caption: ?string, headers: list<string>, rows: list<list<string>>}>, notes: list<string>}
     */
    private function sectionCollectePoints(): array
    {
        $rate = (int) (JokerCollectePointsService::SHARE_RATE * 100);

        return [
            'code' => Joker::CODE_COLLECTE_POINTS,
            'anchor' => $this->anchorForCode(Joker::CODE_COLLECTE_POINTS),
            'title' => $this->t(Joker::CODE_COLLECTE_POINTS),
            'intro' => sprintf(
                'Après « %s » éventuel, l’équipe qui pose ce joker prélève %d %% (arrondi à l’entier) du total prono de chaque autre équipe et s’ajoute cette somme.',
                $this->t(Joker::CODE_PIQUE_POINTS),
                $rate,
            ),
            'tables' => [
                [
                    'caption' => 'Exemple sur un match (votre équipe C pose la collecte)',
                    'headers' => ['Équipe', 'Total prono avant collecte', 'Prélèvement', 'Total après'],
                    'rows' => [
                        ['A', '20', '−2 (10 % de 20)', '18'],
                        ['B', '35', '−4 (10 % de 35)', '31'],
                        ['C (collecteur)', '10', '—', '16 (10 + 2 + 4)'],
                    ],
                ],
                [
                    'caption' => 'Précisions',
                    'headers' => ['Règle', 'Détail'],
                    'rows' => [
                        ['Qui paie', 'Toutes les équipes sauf le collecteur (même si total faible ou nul).'],
                        ['Plancher', 'Si le total d’une équipe est ≤ 0, aucun prélèvement sur elle.'],
                        ['Arrondi', 'Chaque prélèvement = arrondi('.$rate.' % du total de l’équipe).'],
                        ['Plusieurs collecteurs', 'Si plusieurs équipes posent collecte sur le même match, les prélèvements s’enchaînent sur les totaux déjà réduits.'],
                    ],
                ],
            ],
            'notes' => [
                'Joker non offensif : non bloqué par '.$this->protectionPairLabel().'.',
                'Ne taxe pas les points marqués par les buteurs.',
            ],
        ];
    }

    /**
     * @return array{code: string, anchor: string, title: string, intro: string, tables: list<array{caption: ?string, headers: list<string>, rows: list<list<string>>}>, notes: list<string>}
     */
    private function sectionInverseScore(): array
    {
        return [
            'code' => Joker::CODE_INVERSE_SCORE,
            'anchor' => $this->anchorForCode(Joker::CODE_INVERSE_SCORE),
            'title' => $this->t(Joker::CODE_INVERSE_SCORE),
            'intro' => 'Joker offensif : les pronostics de l’équipe ciblée sont notés en inversant domicile et extérieur avant le barème.',
            'tables' => [
                [
                    'caption' => 'Scores effectifs',
                    'headers' => ['Prono saisi', 'Score utilisé pour le barème', 'Remarque'],
                    'rows' => [
                        ['3 – 0', '0 – 3', 'Peut passer d’un bon à un mauvais résultat.'],
                        ['1 – 1', '1 – 1', 'Inchangé (symétrique).'],
                        ['0 – 2', '2 – 0', 'Idem inversion domicile / extérieur.'],
                    ],
                ],
                [
                    'caption' => 'Interactions',
                    'headers' => ['Avec', 'Effet', 'Exemple'],
                    'rows' => [
                        [
                            $this->t(Joker::CODE_DOUBLE_EQUIPE).' (cible)',
                            'Barème calculé sur score inversé, puis ×2 ou malus double.',
                            'Inversion puis double sur le nouveau barème.',
                        ],
                        [
                            $this->t(Joker::CODE_PIQUE_POINTS).' (après coup)',
                            'Le vol porte sur le total prono déjà recalculé.',
                            'Total inversé + éventuel double = base du vol.',
                        ],
                        [
                            'Protection',
                            'Inversion ignorée ; joker consommé.',
                            'La cible garde le barème sur son score saisi.',
                        ],
                    ],
                ],
            ],
            'notes' => [
                'Ne modifie pas les buteurs.',
                'Le « BigBalls » (deux coéquipiers, même score) reste un indicateur à part ; l’inversion change seulement le calcul des points.',
            ],
        ];
    }

    /**
     * @return array{code: string, anchor: string, title: string, intro: string, tables: list<array{caption: ?string, headers: list<string>, rows: list<list<string>>}>, notes: list<string>}
     */
    private function sectionDoubleButeur(): array
    {
        return [
            'code' => Joker::CODE_DOUBLE_BUTEUR,
            'anchor' => $this->anchorForCode(Joker::CODE_DOUBLE_BUTEUR),
            'title' => $this->t(Joker::CODE_DOUBLE_BUTEUR),
            'intro' => 'Sur le match choisi, les buts comptent double pour les buteurs de votre équipe dont le pays joue ce match (au moins un pays parmi domicile / extérieur).',
            'tables' => [
                [
                    'caption' => 'Calcul des points buteur',
                    'headers' => ['Étape', 'Valeur', 'Exemple'],
                    'rows' => [
                        ['Points d’un but (base)', '1 × coefficient de popularité du buteur (max ×5)', 'But = 4 pts'],
                        ['Avec double buteur', '×2 sur ce match', '4 → 8 pts pour votre équipe'],
                    ],
                ],
                [
                    'caption' => 'Indépendance des pronos',
                    'headers' => ['Joker prono sur le même match', 'Impact sur les buteurs'],
                    'rows' => [
                        [
                            sprintf(
                                '%s / %s / %s',
                                $this->t(Joker::CODE_PIQUE_POINTS),
                                $this->t(Joker::CODE_COLLECTE_POINTS),
                                $this->t(Joker::CODE_DOUBLE_EQUIPE),
                            ),
                            'Aucun : les buteurs ne sont ni volés ni taxés.',
                        ],
                        [
                            $this->t(Joker::CODE_INVERSE_BUTEUR).' (subi)',
                            'Les buts peuvent devenir négatifs pour la cible ; le ×2 s’applique avant la négation si vous avez aussi '.$this->t(Joker::CODE_DOUBLE_BUTEUR).'.',
                        ],
                    ],
                ],
            ],
            'notes' => [
                'Posable uniquement si un pays de vos buteurs est présent dans le match.',
                sprintf(
                    'Un buteur français ne remplace pas « %s » : pas de protection automatique via le buteur.',
                    $this->t(Joker::CODE_EQUIPE_FAVORITE),
                ),
            ],
        ];
    }

    /**
     * @return array{code: string, anchor: string, title: string, intro: string, tables: list<array{caption: ?string, headers: list<string>, rows: list<list<string>>}>, notes: list<string>}
     */
    private function sectionInverseButeur(): array
    {
        return [
            'code' => Joker::CODE_INVERSE_BUTEUR,
            'anchor' => $this->anchorForCode(Joker::CODE_INVERSE_BUTEUR),
            'title' => $this->t(Joker::CODE_INVERSE_BUTEUR),
            'intro' => 'Joker offensif : vous ciblez une équipe dont au moins un buteur a un pays qui joue ce match. Les points buteur de cette équipe sur ce match deviennent l’opposé de leur valeur.',
            'tables' => [
                [
                    'caption' => 'Exemples de points buteur sur le match',
                    'headers' => ['But', 'Points normaux', 'Avec inversion (cible)'],
                    'rows' => [
                        ['1 but à 5 pts', '+5', '−5'],
                        ['2 buts à 4 pts chacun', '+8', '−8'],
                    ],
                ],
                [
                    'caption' => 'Interactions',
                    'headers' => ['Avec', 'Effet'],
                    'rows' => [
                        [
                            $this->t(Joker::CODE_DOUBLE_BUTEUR).' (cible, même match)',
                            'Points doublés puis passés en négatif (ex. +10 → −10).',
                        ],
                        [
                            $this->protectionPairLabel().' (cible)',
                            'Inversion sans effet ; joker consommé.',
                        ],
                        [
                            sprintf('%s / %s', $this->t(Joker::CODE_PIQUE_POINTS), $this->t(Joker::CODE_COLLECTE_POINTS)),
                            'Aucun lien : les buteurs ne participent pas au vol ni à la taxe prono.',
                        ],
                    ],
                ],
            ],
            'notes' => [
                'Cumul au classement via le total buteur de l’équipe, pas via pointsEquipe des pronos.',
            ],
        ];
    }

    /**
     * @return array{code: string, anchor: string, title: string, intro: string, tables: list<array{caption: ?string, headers: list<string>, rows: list<list<string>>}>, notes: list<string>}
     */
    private function sectionBouclier(): array
    {
        return [
            'code' => Joker::CODE_BOUCLIER,
            'anchor' => $this->anchorForCode(Joker::CODE_BOUCLIER),
            'title' => $this->t(Joker::CODE_BOUCLIER),
            'intro' => 'Posé sur un match à venir : votre équipe est protégée pour toute la journée calendaire de ce match contre les trois jokers offensifs qui ciblent une équipe.',
            'tables' => [
                [
                    'caption' => 'Jokers neutralisés contre vous (jour concerné)',
                    'headers' => ['Joker adverse', 'Si vous êtes protégé'],
                    'rows' => [
                        [$this->t(Joker::CODE_PIQUE_POINTS), 'Pas de vol ; joker adverse consommé.'],
                        [$this->t(Joker::CODE_INVERSE_SCORE), 'Vos pronos restent notés normalement.'],
                        [$this->t(Joker::CODE_INVERSE_BUTEUR), 'Vos points buteur restent positifs.'],
                    ],
                ],
                [
                    'caption' => 'Jokers qui s’appliquent quand même',
                    'headers' => ['Joker', 'Effet'],
                    'rows' => [
                        [$this->t(Joker::CODE_DOUBLE_EQUIPE).' (adversaire ou vous)', 'Calcul prono habituel.'],
                        [$this->t(Joker::CODE_COLLECTE_POINTS), 'Taxe 10 % sur les totaux prono (si vous n’êtes pas collecteur).'],
                        [$this->t(Joker::CODE_DOUBLE_BUTEUR), 'Multiplicateur buteur habituel.'],
                        [$this->t(Joker::CODE_ESPION), 'Renseignement, sans impact points.'],
                    ],
                ],
            ],
            'notes' => [
                'La protection vaut pour tous les matchs de la même journée, pas seulement celui où ce joker est posé.',
                sprintf('Au retrait ou à la pose de « %s », les matchs terminés de la journée sont recalculés.', $this->t(Joker::CODE_BOUCLIER)),
            ],
        ];
    }

    /**
     * @return array{code: string, anchor: string, title: string, intro: string, tables: list<array{caption: ?string, headers: list<string>, rows: list<list<string>>}>, notes: list<string>}
     */
    private function sectionEquipeFavorite(): array
    {
        return [
            'code' => Joker::CODE_EQUIPE_FAVORITE,
            'anchor' => $this->anchorForCode(Joker::CODE_EQUIPE_FAVORITE),
            'title' => $this->t(Joker::CODE_EQUIPE_FAVORITE),
            'intro' => sprintf(
                'Choix du pays dans Mon compte → Mon équipe, avant le début de la compétition. Même protection que « %s », mais uniquement sur les matchs de phase de groupes où ce pays joue.',
                $this->t(Joker::CODE_BOUCLIER),
            ),
            'tables' => [
                [
                    'caption' => 'Portée',
                    'headers' => ['Contexte', 'Protection active ?'],
                    'rows' => [
                        ['Match de poule France – Allemagne, favorite = France', 'Oui (France joue).'],
                        ['Match de poule sans votre pays', 'Non.'],
                        ['Phase à élimination directe (hors poules)', 'Non, même si votre pays joue.'],
                    ],
                ],
                [
                    'caption' => 'Ce que la favorite ne fait pas',
                    'headers' => ['Élément', 'Comportement'],
                    'rows' => [
                        ['Buteurs du même pays', 'Ne déclenchent pas la protection ; servent au double / inverse buteur.'],
                        [
                            sprintf('%s / %s adverses', $this->t(Joker::CODE_COLLECTE_POINTS), $this->t(Joker::CODE_DOUBLE_EQUIPE)),
                            'S’appliquent normalement.',
                        ],
                        ['Choix du pays', 'Ne consomme pas un emplacement « par match » : enregistrement unique en compte.'],
                    ],
                ],
            ],
            'notes' => [
                sprintf('Secret vis-à-vis des autres équipes (non révélé par « %s »).', $this->t(Joker::CODE_ESPION)),
                'Les trois jokers offensifs ciblant votre équipe sont neutralisés sur les matchs protégés.',
            ],
        ];
    }

    /**
     * @return array{code: string, anchor: string, title: string, intro: string, tables: list<array{caption: ?string, headers: list<string>, rows: list<list<string>>}>, notes: list<string>}
     */
    private function sectionEspion(): array
    {
        return [
            'code' => Joker::CODE_ESPION,
            'anchor' => $this->anchorForCode(Joker::CODE_ESPION),
            'title' => $this->t(Joker::CODE_ESPION),
            'intro' => 'Avant le coup d’envoi : affiche des renseignements sur le match (cotes, jokers déjà posés). Ne modifie aucun point. Joker définitif une fois posé.',
            'tables' => [
                [
                    'caption' => 'Interactions',
                    'headers' => ['Autre joker / règle', 'Lien avec '.$this->t(Joker::CODE_ESPION)],
                    'rows' => [
                        ['Tous les jokers de points', 'Aucun : ce joker n’entre pas dans le pipeline de calcul.'],
                        [$this->t(Joker::CODE_EQUIPE_FAVORITE), 'Non affichée dans l’intel (choix secret).'],
                        ['Autres équipes', 'Peut voir qu’un joker est posé ; pas l’effet bloqué ou non sur une cible protégée tant que le match n’est pas joué.'],
                    ],
                ],
            ],
            'notes' => [
                'Retrait impossible après confirmation.',
            ],
        ];
    }

    /**
     * @return array{title: string, intro: string, table: array{headers: list<string>, rows: list<list<string>>}}
     */
    private function buildCrossMatrix(): array
    {
        $pique = $this->t(Joker::CODE_PIQUE_POINTS);
        $collecte = $this->t(Joker::CODE_COLLECTE_POINTS);
        $invScore = $this->t(Joker::CODE_INVERSE_SCORE);
        $doubleEquipe = $this->t(Joker::CODE_DOUBLE_EQUIPE);
        $doubleButeur = $this->t(Joker::CODE_DOUBLE_BUTEUR);
        $invButeur = $this->t(Joker::CODE_INVERSE_BUTEUR);

        return [
            'title' => 'Tableau croisé (même match)',
            'intro' => 'Lecture : en ligne le joker que vous posez ; en colonne ce qui se passe côté points lorsque l’autre effet est aussi en jeu. Les chiffres sont illustratifs.',
            'table' => [
                'headers' => [
                    'Vous posez →',
                    $pique.' sur vous',
                    $collecte.' (autre équipe)',
                    $invScore.' sur vous',
                    'Protégé ('.$this->protectionPairLabel().')',
                ],
                'rows' => [
                    [
                        $doubleEquipe.' (40 pts)',
                        'Vol de 40 si non protégé',
                        '−10 % de 40 = −4',
                        'Barème sur score inversé puis ×2',
                        sprintf('Vous gardez 40 ; %s / %s sans effet', $pique, $invScore),
                    ],
                    [
                        $pique.' (vous volez 25)',
                        'Échange si ciblage mutuel',
                        'Votre proie à 0 avant taxe des autres',
                        '—',
                        'Vol impossible ; joker perdu',
                    ],
                    [
                        $collecte,
                        'Prélève sur totaux après vol',
                        'Deux '.$collecte.' : prélèvements en chaîne',
                        '—',
                        'Vous pouvez collecter même si protégé',
                    ],
                    [
                        $doubleButeur.' (+8 but)',
                        'Buts non volés',
                        'Buts non taxés',
                        '—',
                        'Buts inchangés si '.$invButeur.' bloquée',
                    ],
                ],
            ],
        ];
    }
}
