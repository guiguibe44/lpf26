<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\TeamRecapCopyCategory;

/**
 * Assemble les textes ludiques du récap (source : {@see TeamRecapCopyProvider} / admin).
 */
final class TeamRecapFunCopy
{
    public function __construct(
        private readonly TeamRecapCopyProvider $copyProvider,
    ) {
    }

    /**
     * @param list<array{nickname: string, points: int}> $rankedPlayers desc
     */
    public function buildIntro(string $teamName, string $periodLabel, int $totalTeamPoints, array $rankedPlayers): string
    {
        $category = match (true) {
            $totalTeamPoints >= 80 => TeamRecapCopyCategory::IntroHigh,
            $totalTeamPoints >= 30 => TeamRecapCopyCategory::IntroMedium,
            $totalTeamPoints > 0 => TeamRecapCopyCategory::IntroLow,
            default => TeamRecapCopyCategory::IntroZero,
        };

        $lines = [];
        $main = $this->copyProvider->randomFromCategory($category, $teamName.$periodLabel);
        if ('' !== $main) {
            $lines[] = $main;
        }

        $worst = $rankedPlayers[\count($rankedPlayers) - 1] ?? null;
        $best = $rankedPlayers[0] ?? null;
        if (
            null !== $worst
            && null !== $best
            && $totalTeamPoints > 0
            && \count($rankedPlayers) >= 2
            && ($best['points'] - $worst['points']) >= 25
        ) {
            $extra = $this->copyProvider->line('intro.extra.worst_laggard', [
                'worst_nickname' => $worst['nickname'],
                'best_nickname' => $best['nickname'],
            ]);
            if ('' !== $extra) {
                $lines[] = $extra;
            }
        }

        if ([] === $lines) {
            return '';
        }

        $pool = $lines;
        $index = abs(crc32($teamName.$periodLabel)) % \count($pool);

        return $pool[$index];
    }

    /**
     * Joueur ayant le moins de points sur la période (mise en avant).
     *
     * @return array{title: string, blurb: string}
     */
    public function buildLaggardCopy(string $nickname, int $points): array
    {
        $titleCode = match (true) {
            0 === $points => 'laggard.title.zero',
            $points <= 10 => 'laggard.title.very_low',
            $points <= 25 => 'laggard.title.low',
            default => 'laggard.title.default',
        };

        $blurbCode = match (true) {
            0 === $points => 'laggard.blurb.zero',
            $points <= 15 => 'laggard.blurb.low',
            default => 'laggard.blurb.default',
        };

        return [
            'title' => $this->copyProvider->line($titleCode),
            'blurb' => $this->copyProvider->line($blurbCode, [
                'nickname' => $nickname,
                'points' => $points,
            ]),
        ];
    }

    public function buildChampionTease(string $bestNickname, int $bestPoints, string $worstNickname, int $gap): string
    {
        $code = match (true) {
            $gap <= 0 => 'champion.tease.tied',
            $gap <= 15 => 'champion.tease.close',
            default => 'champion.tease.large',
        };

        return $this->copyProvider->line($code, [
            'best_nickname' => $bestNickname,
            'best_points' => $bestPoints,
            'worst_nickname' => $worstNickname,
            'gap' => max(0, $gap),
        ]);
    }

    public function buildRankingCheer(int $deltaPositions, int $deltaPoints): ?string
    {
        if ($deltaPositions > 0) {
            return $this->copyProvider->line('ranking.up', [
                'delta_positions' => $deltaPositions,
                'delta_points' => number_format($deltaPoints, 0, ',', ' '),
            ]);
        }

        if ($deltaPositions < 0) {
            return $this->copyProvider->line('ranking.down', [
                'delta_positions_abs' => abs($deltaPositions),
            ]);
        }

        if ($deltaPoints > 0) {
            return $this->copyProvider->line('ranking.same_up');
        }

        return null;
    }

    public function pickIntroLineNote(): string
    {
        return $this->copyProvider->pickIntroLineNote();
    }

    /**
     * @return list<array{label: string, condition: string, lines: list<array{code: string, body: string}>}>
     */
    public function catalogIntroPools(): array
    {
        return [
            [
                'label' => 'Grosse période',
                'condition' => 'Points équipe ≥ 80',
                'lines' => $this->mapCatalogLines(TeamRecapCopyCategory::IntroHigh),
            ],
            [
                'label' => 'Période correcte',
                'condition' => '30 ≤ points < 80',
                'lines' => $this->mapCatalogLines(TeamRecapCopyCategory::IntroMedium),
            ],
            [
                'label' => 'Petite période',
                'condition' => '0 < points < 30',
                'lines' => $this->mapCatalogLines(TeamRecapCopyCategory::IntroLow),
            ],
            [
                'label' => 'Sans points',
                'condition' => '0 pt équipe',
                'lines' => $this->mapCatalogLines(TeamRecapCopyCategory::IntroZero),
            ],
        ];
    }

    /**
     * @return list<array{condition: string, line: string}>
     */
    public function catalogIntroExtras(): array
    {
        return array_map(
            static fn (array $row): array => ['condition' => $row['condition'], 'line' => $row['body']],
            $this->copyProvider->catalogRowsForCategory(TeamRecapCopyCategory::IntroExtra),
        );
    }

    /**
     * @return list<array{condition: string, title: string, blurb: string, example: array{title: string, blurb: string}}>
     */
    public function catalogLaggardVariants(): array
    {
        $examples = [
            ['condition' => '0 pt', 'nickname' => 'Pilou', 'points' => 0],
            ['condition' => '1–10 pts', 'nickname' => 'Pilou', 'points' => 8],
            ['condition' => 'Dernier du duo (ex. 29 pts)', 'nickname' => 'Pilou', 'points' => 29],
        ];

        $rows = [];
        foreach ($examples as $ex) {
            $rows[] = [
                'condition' => $ex['condition'],
                'example' => $this->buildLaggardCopy($ex['nickname'], $ex['points']),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{condition: string, example: string}>
     */
    public function catalogChampionTeases(): array
    {
        return [
            [
                'condition' => 'Égalité',
                'example' => $this->buildChampionTease('Zaza', 40, 'Pilou', 0),
            ],
            [
                'condition' => 'Petit écart',
                'example' => $this->buildChampionTease('Zaza', 40, 'Pilou', 8),
            ],
            [
                'condition' => 'Grand écart',
                'example' => $this->buildChampionTease('Zaza', 58, 'Pilou', 28),
            ],
        ];
    }

    /**
     * @return list<array{condition: string, example: string|null}>
     */
    public function catalogRankingCheers(): array
    {
        return [
            ['condition' => 'Places gagnées', 'example' => $this->buildRankingCheer(3, 47)],
            ['condition' => 'Places perdues', 'example' => $this->buildRankingCheer(-2, 12)],
            ['condition' => 'Même place, pts +', 'example' => $this->buildRankingCheer(0, 25)],
            ['condition' => 'Même place, 0 pt', 'example' => $this->buildRankingCheer(0, 0)],
        ];
    }

    /**
     * @return list<array{code: string, body: string}>
     */
    private function mapCatalogLines(TeamRecapCopyCategory $category): array
    {
        return array_map(
            static fn (array $row): array => ['code' => $row['code'], 'body' => $row['body']],
            $this->copyProvider->catalogRowsForCategory($category),
        );
    }
}
