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
            ->setTitle('Espion')
            ->setTag('intel')
            ->setDescription('Desc')
            ->setTechnicalExplanation("Ligne 1\nLigne 2")
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
        self::assertSame('Espion', $catalog[0]['joker']->getDisplayTitle());
        self::assertSame('intel', $catalog[0]['tag']);
        self::assertSame('Renseignement', $catalog[0]['tag_label']);
        self::assertSame('intel', $catalog[0]['tag_css']);
        self::assertSame(['Ligne 1', 'Ligne 2'], $catalog[0]['details']);
        self::assertTrue($catalog[0]['irreversible']);
    }
}
