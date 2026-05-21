<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\JokerLiveStoryCase;

/**
 * Phrases par défaut si le joker n’a pas de modèles personnalisés en base.
 */
final class JokerLiveStoryTemplateDefaults
{
    /**
     * @return array<string, list<string>>
     */
    public static function global(): array
    {
        return [
            JokerLiveStoryCase::Placed->value => [
                'L’équipe {equipe_poseuse} a posé {joker} sur ce match.',
            ],
            JokerLiveStoryCase::PlacedOnTarget->value => [
                'L’équipe {equipe_poseuse} a posé {joker} sur l’équipe {equipe_cible}.',
            ],
            JokerLiveStoryCase::ShieldActive->value => [
                'L’équipe {equipe_poseuse} a activé {joker} : les attaques joker contre elle passent au frigo !',
            ],
            JokerLiveStoryCase::Neutralized->value => [
                'Aïe aïe aïe… l’équipe {equipe_cible} est blindée : le joker est brûlé sans effet.',
            ],
            JokerLiveStoryCase::PointsGain->value => [
                'Caramba !!! l’équipe {equipe} obtient {points} {points_label}.',
            ],
            JokerLiveStoryCase::PointsLoss->value => [
                'Aïe aïe aïe… l’équipe {equipe} perd {points} {points_label}.',
            ],
            JokerLiveStoryCase::PointsNeutral->value => [
                'Match serré : pas de swing en points{suffixe_buteurs} pour l’équipe {equipe} sur ce score.',
            ],
            JokerLiveStoryCase::PointsGainButeur->value => [
                'Caramba !!! l’équipe {equipe} obtient {points} {points_label} sur les buteurs.',
            ],
            JokerLiveStoryCase::PointsLossButeur->value => [
                'Aïe aïe aïe… l’équipe {equipe} perd {points} {points_label} sur les buteurs.',
            ],
            JokerLiveStoryCase::Espion->value => [
                'L’équipe {equipe_poseuse} a sorti {joker} : petit coup d’œil dans les cartes avant le match, zéro point en jeu mais la classe !',
            ],
        ];
    }
}
