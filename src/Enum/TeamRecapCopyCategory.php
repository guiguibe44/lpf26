<?php

declare(strict_types=1);

namespace App\Enum;

enum TeamRecapCopyCategory: string
{
    case IntroHigh = 'intro_high';
    case IntroMedium = 'intro_medium';
    case IntroLow = 'intro_low';
    case IntroZero = 'intro_zero';
    case IntroExtra = 'intro_extra';
    case LaggardTitle = 'laggard_title';
    case LaggardBlurb = 'laggard_blurb';
    case ChampionTease = 'champion_tease';
    case Ranking = 'ranking';
    case Subject = 'subject';

    public function label(): string
    {
        return match ($this) {
            self::IntroHigh => 'Accroche — grosse période (≥ 80 pts)',
            self::IntroMedium => 'Accroche — période correcte (30–79 pts)',
            self::IntroLow => 'Accroche — petite période (1–29 pts)',
            self::IntroZero => 'Accroche — 0 pt équipe',
            self::IntroExtra => 'Accroche — ajout (écart entre coéquipiers)',
            self::LaggardTitle => 'Mise en avant — titre (joueur le moins de pts)',
            self::LaggardBlurb => 'Mise en avant — texte sous le titre',
            self::ChampionTease => 'Mise en avant — rappel du meilleur coéquipier',
            self::Ranking => 'Classement — phrase d’ambiance',
            self::Subject => 'Objet de l’e-mail',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function choices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }
}
