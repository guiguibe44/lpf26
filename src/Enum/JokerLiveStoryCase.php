<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Cas de phrases affichées sur le détail match live / terminé.
 */
enum JokerLiveStoryCase: string
{
    case Placed = 'placed';
    case PlacedOnTarget = 'placed_on_target';
    case ShieldActive = 'shield_active';
    case Neutralized = 'neutralized';
    case PointsGain = 'points_gain';
    case PointsLoss = 'points_loss';
    case PointsNeutral = 'points_neutral';
    case PointsGainButeur = 'points_gain_buteur';
    case PointsLossButeur = 'points_loss_buteur';
    case Espion = 'espion';

    public function label(): string
    {
        return match ($this) {
            self::Placed => 'Pose sur le match',
            self::PlacedOnTarget => 'Pose sur une équipe cible',
            self::ShieldActive => 'Bouclier actif',
            self::Neutralized => 'Joker offensif neutralisé',
            self::PointsGain => 'Points gagnés (équipe concernée)',
            self::PointsLoss => 'Points perdus (équipe concernée)',
            self::PointsNeutral => 'Aucun swing de points',
            self::PointsGainButeur => 'Points buteurs gagnés',
            self::PointsLossButeur => 'Points buteurs perdus',
            self::Espion => 'Espion (sans impact points)',
        };
    }

    public function adminHelp(): string
    {
        return match ($this) {
            self::Placed => 'Quand le joker est posé sans cible adverse (ex. double équipe, double buteur).',
            self::PlacedOnTarget => 'Quand le joker vise une autre équipe (pique, inversion score/buteur).',
            self::ShieldActive => 'Affichée quand le bouclier est actif sur ce match.',
            self::Neutralized => 'Affichée si le joker est bloqué (bouclier ou équipe favorite adverse).',
            self::PointsGain => 'Une ligne par équipe qui gagne des points pronos (répéter la ligne si besoin).',
            self::PointsLoss => 'Une ligne par équipe qui perd des points pronos.',
            self::PointsNeutral => 'Si le calcul ne change pas les points pronos.',
            self::PointsGainButeur => 'Équipe qui gagne des points buteurs sur ce match.',
            self::PointsLossButeur => 'Équipe qui perd des points buteurs.',
            self::Espion => 'Affichée quand l’espion est posé (sans impact points).',
        };
    }

    /**
     * Nom de propriété formulaire admin (camelCase).
     */
    public function adminProperty(): string
    {
        return match ($this) {
            self::Placed => 'liveStoryPlaced',
            self::PlacedOnTarget => 'liveStoryPlacedOnTarget',
            self::ShieldActive => 'liveStoryShieldActive',
            self::Neutralized => 'liveStoryNeutralized',
            self::PointsGain => 'liveStoryPointsGain',
            self::PointsLoss => 'liveStoryPointsLoss',
            self::PointsNeutral => 'liveStoryPointsNeutral',
            self::PointsGainButeur => 'liveStoryPointsGainButeur',
            self::PointsLossButeur => 'liveStoryPointsLossButeur',
            self::Espion => 'liveStoryEspion',
        };
    }
}
