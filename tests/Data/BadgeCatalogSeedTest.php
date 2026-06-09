<?php

declare(strict_types=1);

namespace App\Tests\Data;

use App\Data\BadgeCatalogSeed;
use App\Enum\BadgeCategory;
use PHPUnit\Framework\TestCase;

final class BadgeCatalogSeedTest extends TestCase
{
    public function testDefinitionsAreUniqueAndComplete(): void
    {
        $definitions = BadgeCatalogSeed::definitions();
        self::assertCount(46, $definitions);

        $codes = array_column($definitions, 'code');
        self::assertSame(count($codes), count(array_unique($codes)));

        $categories = array_map(static fn (array $row): BadgeCategory => $row['category'], $definitions);
        self::assertContains(BadgeCategory::Jokers, $categories);
        self::assertContains(BadgeCategory::Vendee, $categories);
    }
}
