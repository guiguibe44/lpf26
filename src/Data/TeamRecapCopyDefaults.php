<?php

declare(strict_types=1);

namespace App\Data;

use App\Enum\TeamRecapCopyCategory;

/**
 * Textes par défaut du récap d’équipe (amorçage BDD + repli si ligne désactivée).
 *
 * @return list<array{
 *     code: string,
 *     category: TeamRecapCopyCategory,
 *     adminLabel: string,
 *     conditionHint: ?string,
 *     body: string,
 *     sortOrder: int
 * }>
 */
final class TeamRecapCopyDefaults
{
    public static function entries(): array
    {
        return array_merge(
            self::introPools(),
            self::introExtras(),
            self::laggardTitles(),
            self::laggardBlurbs(),
            self::championTeases(),
            self::rankingLines(),
            self::subjects(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function bodiesByCode(): array
    {
        $map = [];
        foreach (self::entries() as $entry) {
            $map[$entry['code']] = $entry['body'];
        }

        return $map;
    }

    /**
     * @return list<array{code: string, category: TeamRecapCopyCategory, adminLabel: string, conditionHint: ?string, body: string, sortOrder: int}>
     */
    private static function introPools(): array
    {
        return [
            ...self::lines(TeamRecapCopyCategory::IntroHigh, 'intro.high', 'Accroche forte', 80, [
                'Votre équipe a enchaîné comme une machine à caféine et à pronos ! ☕⚽',
                'Le classement a dû trembler : grosse fournée de points sur la période.',
                'On dirait que quelqu\'a lu le scénario du match avant tout le monde…',
            ]),
            ...self::lines(TeamRecapCopyCategory::IntroMedium, 'intro.medium', 'Accroche correcte', 30, [
                'Pas mal du tout : votre duo a gratté des points sans faire de vague inutile.',
                'La période était correcte — ni festival, ni catastrophe annoncée.',
                'Entre deux matchs, vous avez quand même fait parler la calculette LPF.',
            ]),
            ...self::lines(TeamRecapCopyCategory::IntroLow, 'intro.low', 'Accroche modeste', 10, [
                'Des petits points ici et là : la saison n\'est pas finie, loin de là !',
                'Ce n\'était pas la folie, mais vous restez dans la course avec le sourire.',
                'La période a été sage… la prochaine sera peut-être plus bruyante.',
            ]),
            ...self::lines(TeamRecapCopyCategory::IntroZero, 'intro.zero', 'Accroche sans points', 0, [
                'Période calme côté points — le prochain récap promet mieux, on le sent.',
                'Pas de jackpot cette fois : place aux revanches sur les prochains matchs !',
                'Même les légendes ont des trous dans la raquette… et dans leurs pronos.',
            ]),
        ];
    }

    /**
     * @return list<array{code: string, category: TeamRecapCopyCategory, adminLabel: string, conditionHint: ?string, body: string, sortOrder: int}>
     */
    private static function introExtras(): array
    {
        return [
            [
                'code' => 'intro.extra.worst_laggard',
                'category' => TeamRecapCopyCategory::IntroExtra,
                'adminLabel' => 'Ajout — gros écart au sein du duo',
                'conditionHint' => 'Le joueur le moins de pts a ≥ 25 pts de moins que le meilleur',
                'body' => 'Côté duo : <strong>{worst_nickname}</strong> a besoin d’un reboot pronos, <strong>{best_nickname}</strong> tient la baraque.',
                'sortOrder' => 0,
            ],
        ];
    }

    /**
     * @return list<array{code: string, category: TeamRecapCopyCategory, adminLabel: string, conditionHint: ?string, body: string, sortOrder: int}>
     */
    private static function laggardTitles(): array
    {
        return [
            [
                'code' => 'laggard.title.zero',
                'category' => TeamRecapCopyCategory::LaggardTitle,
                'adminLabel' => 'Titre — 0 pt sur la période',
                'conditionHint' => '0 pt pour le joueur mis en avant',
                'body' => '👆 Profil basse consommation',
                'sortOrder' => 0,
            ],
            [
                'code' => 'laggard.title.very_low',
                'category' => TeamRecapCopyCategory::LaggardTitle,
                'adminLabel' => 'Titre — 1 à 10 pts',
                'conditionHint' => '1–10 pts',
                'body' => '👆 Doigt mouillé du classement interne',
                'sortOrder' => 1,
            ],
            [
                'code' => 'laggard.title.low',
                'category' => TeamRecapCopyCategory::LaggardTitle,
                'adminLabel' => 'Titre — 11 à 25 pts',
                'conditionHint' => '11–25 pts',
                'body' => '👆 Pépinière à points (version solo)',
                'sortOrder' => 2,
            ],
            [
                'code' => 'laggard.title.default',
                'category' => TeamRecapCopyCategory::LaggardTitle,
                'adminLabel' => 'Titre — défaut',
                'conditionHint' => '26+ pts mais dernier du duo',
                'body' => '👆 Moins bonne pioche du duo',
                'sortOrder' => 3,
            ],
        ];
    }

    /**
     * @return list<array{code: string, category: TeamRecapCopyCategory, adminLabel: string, conditionHint: ?string, body: string, sortOrder: int}>
     */
    private static function laggardBlurbs(): array
    {
        return [
            [
                'code' => 'laggard.blurb.zero',
                'category' => TeamRecapCopyCategory::LaggardBlurb,
                'adminLabel' => 'Texte — 0 pt',
                'conditionHint' => '0 pt',
                'body' => '<strong>{nickname}</strong> termine la période à <strong>0 pt</strong> : le compteur n’a pas bronché. Prochaine fois, on espère du vert sur la feuille !',
                'sortOrder' => 0,
            ],
            [
                'code' => 'laggard.blurb.low',
                'category' => TeamRecapCopyCategory::LaggardBlurb,
                'adminLabel' => 'Texte — 1 à 15 pts',
                'conditionHint' => '1–15 pts',
                'body' => '<strong>{nickname}</strong> a le moins nourri le pot commun avec <strong>{points} pt</strong> — la prochaine fournée peut tout changer.',
                'sortOrder' => 1,
            ],
            [
                'code' => 'laggard.blurb.default',
                'category' => TeamRecapCopyCategory::LaggardBlurb,
                'adminLabel' => 'Texte — défaut',
                'conditionHint' => 'Dernier du duo avec 16+ pts',
                'body' => 'Sur la période, <strong>{nickname}</strong> est celui qui a le moins gagné (<strong>{points} pt</strong>) : le doigt mouillé, c’est pour encourager la revanche !',
                'sortOrder' => 2,
            ],
        ];
    }

    /**
     * @return list<array{code: string, category: TeamRecapCopyCategory, adminLabel: string, conditionHint: ?string, body: string, sortOrder: int}>
     */
    private static function championTeases(): array
    {
        return [
            [
                'code' => 'champion.tease.tied',
                'category' => TeamRecapCopyCategory::ChampionTease,
                'adminLabel' => 'Rappel meilleur — égalité',
                'conditionHint' => 'Même total de pts entre les deux',
                'body' => 'Égalité parfaite avec <strong>{best_nickname}</strong> — prochain match pour départager le duo.',
                'sortOrder' => 0,
            ],
            [
                'code' => 'champion.tease.close',
                'category' => TeamRecapCopyCategory::ChampionTease,
                'adminLabel' => 'Rappel meilleur — petit écart',
                'conditionHint' => 'Écart 1–15 pts',
                'body' => 'Pour relativiser : <strong>{best_nickname}</strong> mène de justesse avec <strong>{best_points} pt</strong> (écart {gap} pt).',
                'sortOrder' => 1,
            ],
            [
                'code' => 'champion.tease.large',
                'category' => TeamRecapCopyCategory::ChampionTease,
                'adminLabel' => 'Rappel meilleur — grand écart',
                'conditionHint' => 'Écart > 15 pts',
                'body' => 'Pendant ce temps, <strong>{best_nickname}</strong> a engrangé <strong>{best_points} pt</strong> — il y a de la marge pour la remontada !',
                'sortOrder' => 2,
            ],
        ];
    }

    /**
     * @return list<array{code: string, category: TeamRecapCopyCategory, adminLabel: string, conditionHint: ?string, body: string, sortOrder: int}>
     */
    private static function rankingLines(): array
    {
        return [
            [
                'code' => 'ranking.up',
                'category' => TeamRecapCopyCategory::Ranking,
                'adminLabel' => 'Classement — places gagnées',
                'conditionHint' => 'delta positions > 0',
                'body' => 'Vous avez grimpé de <strong>{delta_positions}</strong> place(s) au classement (+ {delta_points} pts au total). Chapeau !',
                'sortOrder' => 0,
            ],
            [
                'code' => 'ranking.down',
                'category' => TeamRecapCopyCategory::Ranking,
                'adminLabel' => 'Classement — places perdues',
                'conditionHint' => 'delta positions < 0',
                'body' => 'Petit passage à vide au classement ({delta_positions_abs} place(s)) — le prochain récap sera la revanche.',
                'sortOrder' => 1,
            ],
            [
                'code' => 'ranking.same_up',
                'category' => TeamRecapCopyCategory::Ranking,
                'adminLabel' => 'Classement — même place, pts en hausse',
                'conditionHint' => 'delta positions = 0 et pts > 0',
                'body' => 'Même rang qu\'avant, mais le compteur avance — la pression monte derrière vous.',
                'sortOrder' => 2,
            ],
        ];
    }

    /**
     * @return list<array{code: string, category: TeamRecapCopyCategory, adminLabel: string, conditionHint: ?string, body: string, sortOrder: int}>
     */
    private static function subjects(): array
    {
        return [
            [
                'code' => 'subject.hot',
                'category' => TeamRecapCopyCategory::Subject,
                'adminLabel' => 'Objet — grosse période équipe',
                'conditionHint' => '≥ 50 pts équipe',
                'body' => 'LPF\'26 — {team_name} : +{total_points} pts (👆 {laggard_nickname} en embuscade)',
                'sortOrder' => 0,
            ],
            [
                'code' => 'subject.positive',
                'category' => TeamRecapCopyCategory::Subject,
                'adminLabel' => 'Objet — points > 0',
                'conditionHint' => '0 < pts < 50',
                'body' => 'LPF\'26 — Récap {team_name} : +{total_points} pts sur la période',
                'sortOrder' => 1,
            ],
            [
                'code' => 'subject.neutral',
                'category' => TeamRecapCopyCategory::Subject,
                'adminLabel' => 'Objet — 0 pt',
                'conditionHint' => '0 pt équipe',
                'body' => 'LPF\'26 — Récap {team_name} ({period_label})',
                'sortOrder' => 2,
            ],
        ];
    }

    /**
     * @param list<string> $bodies
     *
     * @return list<array{code: string, category: TeamRecapCopyCategory, adminLabel: string, conditionHint: ?string, body: string, sortOrder: int}>
     */
    private static function lines(
        TeamRecapCopyCategory $category,
        string $codePrefix,
        string $labelPrefix,
        int $pointsHint,
        array $bodies,
    ): array {
        $rows = [];
        foreach ($bodies as $index => $body) {
            $rows[] = [
                'code' => sprintf('%s.%d', $codePrefix, $index),
                'category' => $category,
                'adminLabel' => sprintf('%s — variante %d', $labelPrefix, $index + 1),
                'conditionHint' => sprintf('Palier points équipe (voir catégorie), variante %d', $pointsHint),
                'body' => $body,
                'sortOrder' => $index,
            ];
        }

        return $rows;
    }
}
