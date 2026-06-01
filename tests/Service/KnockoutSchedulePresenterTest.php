<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Data\WorldCup2026KnockoutFixtures;
use App\Service\KnockoutFixtureLabelResolver;
use App\Service\KnockoutSchedulePresenter;
use PHPUnit\Framework\TestCase;

final class KnockoutSchedulePresenterTest extends TestCase
{
    public function testBuildBracketAlignsFeedersFromFifaTree(): void
    {
        $presenter = new KnockoutSchedulePresenter(new KnockoutFixtureLabelResolver());
        $bracket = $presenter->buildBracket();

        self::assertSame(16, $bracket['grid_rows']);
        self::assertCount(4, $bracket['columns']);

        $r32 = $this->findColumn($bracket['columns'], WorldCup2026KnockoutFixtures::ROUND_OF_32);
        $r16 = $this->findColumn($bracket['columns'], WorldCup2026KnockoutFixtures::ROUND_OF_16);

        self::assertCount(16, $r32['matches']);
        self::assertSame(1, $r32['matches'][0]['row_start']);
        self::assertSame(1, $r32['matches'][0]['row_span']);
        self::assertSame(16, $r32['matches'][15]['row_start']);

        // M89 : vainqueurs M73 (ligne 1) et M75 (ligne 3)
        self::assertSame(1, $r16['matches'][0]['row_start']);
        self::assertSame(3, $r16['matches'][0]['row_span']);

        // M90 : vainqueurs M74 (ligne 2) et M77 (ligne 5)
        self::assertSame(2, $r16['matches'][1]['row_start']);
        self::assertSame(4, $r16['matches'][1]['row_span']);

        self::assertNotNull($bracket['final_match']);
        self::assertNotNull($bracket['third_place_match']);
    }

    /**
     * @param list<array<string, mixed>> $columns
     *
     * @return array<string, mixed>
     */
    private function findColumn(array $columns, string $key): array
    {
        foreach ($columns as $column) {
            if ($column['key'] === $key) {
                return $column;
            }
        }

        self::fail(sprintf('Colonne %s introuvable.', $key));
    }
}
