<?php

declare(strict_types=1);

namespace App\Data;

use App\Enum\BadgeCategory;
use App\Enum\BadgeOutcome;
use App\Enum\BadgeScope;

/**
 * Catalogue initial des badges (shortlist v1).
 * Les noms affichés sont éditables en admin ; code et critère restent stables.
 */
final class BadgeCatalogSeed
{
    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     category: BadgeCategory,
     *     scope: BadgeScope,
     *     outcome: ?BadgeOutcome,
     *     criterionHint: string,
     *     flavorText: ?string,
     *     icon: ?string,
     *     ironic: bool,
     *     sortOrder: int,
     * }>
     */
    public static function definitions(): array
    {
        return [
            ...self::pronosticBadges(),
            ...self::resultatBadges(),
            ...self::bigBallsBadges(),
            ...self::jokerBadges(),
            ...self::classementBadges(),
            ...self::vendeeBadges(),
            ...self::competitionBadges(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function pronosticBadges(): array
    {
        $category = BadgeCategory::Pronostic;

        return [
            self::row('seul_au_monde', 'Seul au Monde à Penser Ça', $category, BadgeScope::Player, null,
                'Score unique sur un match (seul à pronostiquer ce score).', null, 'ti-user-star', false, 10),
            self::row('cote_malade', 'Cote de Malade, Ego +1000', $category, BadgeScope::Player, null,
                'Bon 1/N/2 sur une cote ≥ 3.', null, 'ti-chart-arrows-vertical', false, 20),
            self::row('prono_ensemble_dispute', 'On Pronostique Ensemble ou On Se Dispute', $category, BadgeScope::Team, null,
                'Les 2 coéquipiers ont pronostiqué tous les matchs d\'une journée.', null, 'ti-users', false, 30),
            self::row('ame_defenseur', '0-0 Partout, Âme de Défenseur', $category, BadgeScope::Player, null,
                '≥ 5 pronos 0-0.', null, 'ti-shield', true, 40),
            self::row('ame_attaquant', '4-3 à Chaque Fois, Âme d\'Attaquant', $category, BadgeScope::Player, null,
                '≥ 5 pronos avec 4+ buts d\'un côté.', null, 'ti-ball-football', false, 50),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function resultatBadges(): array
    {
        $category = BadgeCategory::Resultats;

        return [
            self::row('hat_trick_dingues', 'Hat-trick de Dingues', $category, BadgeScope::Player, null,
                '3 scores exacts sur une journée.', '3 scores exacts en une soirée. T\'as dû tricher.', 'ti-flame', false, 10),
            self::row('premier_exact', 'Premier Exact du Tournoi', $category, BadgeScope::Player, null,
                '1er score exact de la compétition.', null, 'ti-trophy', false, 20),
            self::row('chasseur_exact', 'Chasseur de Scores Exact', $category, BadgeScope::Player, null,
                '10 scores exacts cumulés.', null, 'ti-target', false, 30),
            self::row('machine_30', 'Machine à 30 Points', $category, BadgeScope::Player, null,
                '25 scores exacts cumulés.', null, 'ti-bolt', false, 40),
            self::row('legende_bareme', 'Légende du Barème', $category, BadgeScope::Player, null,
                '50 scores exacts cumulés.', null, 'ti-crown', false, 50),
            self::row('var_ah_si', 'VAR a Dit Non… Ah Si', $category, BadgeScope::Player, null,
                'Score exact malgré un joker inverse score subi.', null, 'ti-device-tv', false, 60),
            self::row('bouclier_humiliation', 'Bouclier Anti-Humiliation', $category, BadgeScope::Team, BadgeOutcome::Positive,
                'Joker adverse neutralisé (bouclier ou équipe favorite).', null, 'ti-shield-check', false, 70),
            self::row('cinq_rates', '5 Ratés d\'Affilée, Chapeau Bas', $category, BadgeScope::Player, BadgeOutcome::Negative,
                '5 pronos 0 pt consécutifs.', null, 'ti-mood-sad', true, 80),
            self::row('but_contre_camp', 'But Contre Son Camp', $category, BadgeScope::Player, BadgeOutcome::Negative,
                'Pire journée du championnat (moins de pts).', null, 'ti-arrow-down', true, 90),
            self::row('remontada', 'Remontada', $category, BadgeScope::Player, BadgeOutcome::Positive,
                '+20 pts vs journée précédente.', null, 'ti-trending-up', false, 100),
            self::row('effondrement_psg', 'Effondrement PSG', $category, BadgeScope::Player, BadgeOutcome::Negative,
                '−20 pts vs journée précédente.', '−20 pts en 24 h. Même les parisiens comprennent.', 'ti-trending-down', true, 110),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function bigBallsBadges(): array
    {
        $category = BadgeCategory::BigBalls;

        return [
            self::row('meme_prono_delire', 'Même Prono, Même Délire', $category, BadgeScope::Team, null,
                '1er BigBalls tenté (les 2 coéquipiers, même score).', null, 'ti-copy', false, 10),
            self::row('telepathie_vestiaire', 'Télépathie de Vestiaire', $category, BadgeScope::Team, BadgeOutcome::Positive,
                'BigBalls réussi (exact ou bon 1/N/2).', null, 'ti-brain', false, 20),
            self::row('duo_legende', 'Duo de Légende (ou pas)', $category, BadgeScope::Team, BadgeOutcome::Positive,
                '5 BigBalls réussis cumulés.', null, 'ti-medal', false, 30),
            self::row('double_mise_honte', 'Double Mise, Double Honte', $category, BadgeScope::Team, BadgeOutcome::Negative,
                'BigBalls tenté mais raté (0 pt).', 'BigBalls tenté. BigBalls raté. BigBalls regretté.', 'ti-mood-cry', true, 40),
            self::row('on_a_ose', 'On a Osé, On a Gagné', $category, BadgeScope::Team, BadgeOutcome::Positive,
                'BigBalls réussi sur un score exact.', null, 'ti-sparkles', false, 50),
            self::row('trois_fois_affilee', '3 Fois d\'Affilée, C\'est Plus du Hasard', $category, BadgeScope::Team, BadgeOutcome::Positive,
                '3 BigBalls réussis consécutifs.', null, 'ti-repeat', false, 60),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function jokerBadges(): array
    {
        $category = BadgeCategory::Jokers;

        return [
            self::row('pickpocket_points', 'Pickpocket de Points', $category, BadgeScope::Team, BadgeOutcome::Positive,
                'Joker Pique Points réussi (points volés).', null, 'ti-hand-grab', false, 10),
            self::row('double_equipe_peine', 'Double Équipe, Double Peine', $category, BadgeScope::Team, BadgeOutcome::Positive,
                'Joker Double Équipe réussi.', null, 'ti-users-group', false, 20),
            self::row('bouclier_anti_chambre', 'Bouclier Anti-Chambre', $category, BadgeScope::Team, BadgeOutcome::Positive,
                'Bouclier ou équipe favorite neutralise un joker adverse.', null, 'ti-shield', false, 30),
            self::row('collecte_taxes', 'Collecte de Taxes', $category, BadgeScope::Team, BadgeOutcome::Positive,
                'Joker Collecte Points rentable.', null, 'ti-coins', false, 40),
            self::row('banque_gagne', 'La Banque Gagne Toujours', $category, BadgeScope::Team, BadgeOutcome::Positive,
                '+30 pts nets grâce aux jokers sur un match.', null, 'ti-building-bank', false, 50),
            self::row('var_inverse', 'VAR Inversé', $category, BadgeScope::Team, BadgeOutcome::Positive,
                'Joker Inverse Score décisif (résultat favorable).', null, 'ti-arrows-left-right', false, 60),
            self::row('joker_dans_le_mur', 'Joker dans le Mur', $category, BadgeScope::Team, BadgeOutcome::Negative,
                'Joker posé mais bilan net négatif sur le match.', null, 'ti-wall', false, 70),
            self::row('victime_collaterale', 'Victime Collatérale', $category, BadgeScope::Team, BadgeOutcome::Negative,
                '3 jokers adverses subis sur la compétition.', null, 'ti-alert-triangle', true, 80),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function classementBadges(): array
    {
        $category = BadgeCategory::Classement;

        return [
            self::row('montee_express', 'Montée Express', $category, BadgeScope::Team, BadgeOutcome::Positive,
                '+5 places en une journée.', null, 'ti-elevator', false, 10),
            self::row('chute_libre', 'Chute Libre', $category, BadgeScope::Team, BadgeOutcome::Negative,
                '−5 places en une journée.', null, 'ti-parachute', true, 20),
            self::row('top10_regulier', 'Top 10 Régulier', $category, BadgeScope::Team, BadgeOutcome::Positive,
                'Top 10 pendant 5 snapshots consécutifs.', null, 'ti-chart-line', false, 30),
            self::row('outsider_peur', 'Outsider qui Fait Peur', $category, BadgeScope::Team, BadgeOutcome::Positive,
                'Hors top 20 au début, top 10 en phase finale.', null, 'ti-ghost', false, 40),
            self::row('departage_bigballs', 'Départagé au BigBalls', $category, BadgeScope::Team, BadgeOutcome::Positive,
                'Place gagnée au tie-break BigBalls.', null, 'ti-scale', false, 50),
            self::row('on_vous_avait_dit', 'On Vous Avait Dit', $category, BadgeScope::Team, BadgeOutcome::Negative,
                'A été 1er puis rechuté hors top 5.', null, 'ti-mood-confuzed', true, 60),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function vendeeBadges(): array
    {
        $category = BadgeCategory::Vendee;

        return [
            self::row('prefou_or', 'Préfou d\'Or', $category, BadgeScope::Team, BadgeOutcome::Positive,
                '1ère place du classement vendéen.', null, 'ti-crown', false, 10),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function competitionBadges(): array
    {
        $category = BadgeCategory::Competition;

        return [
            self::row('pays_hote_prono', 'Pays Hôte, Prono Local', $category, BadgeScope::Player, BadgeOutcome::Positive,
                'Score exact sur un match impliquant USA, Canada ou Mexique.', null, 'ti-flag', false, 10),
            self::row('tour_3_hotes', 'Tour des 3 Hôtes', $category, BadgeScope::Player, BadgeOutcome::Positive,
                'Score exact sur un match de chaque pays hôte.', null, 'ti-map', false, 20),
            self::row('phase_groupes_survecu', 'Phase de Groupes Survécu', $category, BadgeScope::Player, null,
                'Pronos sur tous les matchs de la phase de groupes.', null, 'ti-checklist', false, 30),
            self::row('knockout_genoux', 'Knockout, Genoux qui Flageolent', $category, BadgeScope::Player, BadgeOutcome::Positive,
                'Score exact en élimination directe.', null, 'ti-legs', false, 40),
            self::row('finale_ou_rien', 'Finale ou Rien', $category, BadgeScope::Player, null,
                'Prono posé sur la finale.', null, 'ti-confetti', false, 50),
            self::row('buteur_ballon_or', 'Buteur Choisi, Ballon d\'Or Imaginaire', $category, BadgeScope::Player, BadgeOutcome::Positive,
                'Buteur choisi a marqué en compétition.', null, 'ti-ball-football', false, 60),
            self::row('panenka_prono', 'Panenka sur le Prono', $category, BadgeScope::Player, BadgeOutcome::Positive,
                'Score exact audacieux (cote max du match).', null, 'ti-run', false, 70),
            self::row('clean_sheet_obsession', 'Clean Sheet Obsession', $category, BadgeScope::Player, BadgeOutcome::Positive,
                '5 exacts 0-0 ou clean sheets.', null, 'ti-lock', false, 80),
            self::row('zizou_mode', 'Zizou Mode', $category, BadgeScope::Player, BadgeOutcome::Positive,
                '3 scores exacts consécutifs.', null, 'ti-star', false, 90),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function row(
        string $code,
        string $name,
        BadgeCategory $category,
        BadgeScope $scope,
        ?BadgeOutcome $outcome,
        string $criterionHint,
        ?string $flavorText,
        ?string $icon,
        bool $ironic,
        int $sortOrder,
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'scope' => $scope,
            'outcome' => $outcome,
            'criterionHint' => $criterionHint,
            'flavorText' => $flavorText,
            'icon' => $icon,
            'ironic' => $ironic,
            'sortOrder' => $sortOrder,
        ];
    }
}
