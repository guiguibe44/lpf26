<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Dégradé de la zone visuelle (classes CSS site-intro-slide__visual--*).
 */
enum SiteIntroVisualTheme: string
{
    case Welcome = 'welcome';
    case Team = 'team';
    case Prono = 'prono';
    case Points = 'points';
    case Bigballs = 'bigballs';
    case Buteur = 'buteur';
    case Jokers = 'jokers';
    case Ranking = 'ranking';
    case Go = 'go';
    case Neutral = 'neutral';
    case None = 'none';

    /**
     * @return array<string, string> libellé => valeur
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }

    public function label(): string
    {
        return match ($this) {
            self::Welcome => 'Bleu (accueil)',
            self::Team => 'Vert (équipe)',
            self::Prono => 'Bleu foot',
            self::Points => 'Orange (points)',
            self::Bigballs => 'Orange vif',
            self::Buteur => 'Violet',
            self::Jokers => 'Violet jokers',
            self::Ranking => 'Or',
            self::Go => 'Vert victoire',
            self::Neutral => 'Gris neutre',
            self::None => 'Aucun (image seule, sans fond coloré)',
        };
    }
}
