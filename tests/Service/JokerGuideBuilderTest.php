<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Joker;
use App\Repository\JokerRepository;
use App\Service\JokerGuideBuilder;
use PHPUnit\Framework\TestCase;

final class JokerGuideBuilderTest extends TestCase
{
    public function testBuildCatalogSkipsInactiveJokers(): void
    {
        $active = (new Joker())
            ->setCode(Joker::CODE_ESPION)
            ->setName('Espion')
            ->setDescription('Desc')
            ->setActive(true)
            ->setSortOrder(1);

        $inactive = (new Joker())
            ->setCode('legacy')
            ->setName('Legacy')
            ->setActive(false)
            ->setSortOrder(99);

        $repo = $this->createMock(JokerRepository::class);
        $repo->method('findAllOrdered')->willReturn([$active, $inactive]);

        $catalog = (new JokerGuideBuilder($repo))->buildCatalog();

        self::assertCount(1, $catalog);
        self::assertSame('Espion', $catalog[0]['joker']->getName());
        self::assertSame('intel', $catalog[0]['category']);
        self::assertTrue($catalog[0]['irreversible']);
    }
}
