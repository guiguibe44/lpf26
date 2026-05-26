<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Joker;
use App\Repository\JokerRepository;
use App\Service\JokerInteractionsGuideBuilder;
use PHPUnit\Framework\TestCase;

final class JokerInteractionsGuideBuilderTest extends TestCase
{
    public function testBuildUsesDisplayTitleFromDatabase(): void
    {
        $bouclier = (new Joker())
            ->setCode(Joker::CODE_BOUCLIER)
            ->setTitle('Dôme d\'or')
            ->setName('Dôme d\'or');

        $repo = $this->createMock(JokerRepository::class);
        $repo->method('findAllOrdered')->willReturn([$bouclier]);

        $guide = (new JokerInteractionsGuideBuilder($repo))->build();

        $section = null;
        foreach ($guide['jokers'] as $row) {
            if (Joker::CODE_BOUCLIER === $row['code']) {
                $section = $row;
                break;
            }
        }

        self::assertNotNull($section);
        self::assertSame('Dôme d\'or', $section['title']);
        self::assertArrayHasKey('image', $section);
        self::assertArrayHasKey('icon_class', $section);

        $tocLabel = null;
        foreach ($guide['toc'] as $item) {
            if ('guide-joker-bouclier' === $item['id']) {
                $tocLabel = $item['label'];
                break;
            }
        }

        self::assertSame('Dôme d\'or', $tocLabel);
    }

    public function testBuildContainsAllJokerSectionsAndPipeline(): void
    {
        $repo = $this->createMock(JokerRepository::class);
        $repo->method('findAllOrdered')->willReturn([]);

        $guide = (new JokerInteractionsGuideBuilder($repo))->build();

        self::assertArrayHasKey('pipeline', $guide);
        self::assertArrayHasKey('cross_matrix', $guide);
        self::assertCount(9, $guide['jokers']);

        $codes = array_column($guide['jokers'], 'code');
        self::assertContains(Joker::CODE_DOUBLE_EQUIPE, $codes);
        self::assertContains(Joker::CODE_PIQUE_POINTS, $codes);
        self::assertContains(Joker::CODE_COLLECTE_POINTS, $codes);

        $pique = null;
        foreach ($guide['jokers'] as $section) {
            if (Joker::CODE_PIQUE_POINTS === $section['code']) {
                $pique = $section;
                break;
            }
        }

        self::assertNotNull($pique);
        self::assertSame('guide-joker-pique-points', $pique['anchor']);
        self::assertNotEmpty($pique['tables']);
    }

    public function testButeurJokerSectionsUseTenPointBaseExamples(): void
    {
        $repo = $this->createMock(JokerRepository::class);
        $repo->method('findAllOrdered')->willReturn([]);

        $guide = (new JokerInteractionsGuideBuilder($repo))->build();

        $doubleButeur = null;
        $inverseButeur = null;
        foreach ($guide['jokers'] as $section) {
            if (Joker::CODE_DOUBLE_BUTEUR === $section['code']) {
                $doubleButeur = $section;
            }
            if (Joker::CODE_INVERSE_BUTEUR === $section['code']) {
                $inverseButeur = $section;
            }
        }

        self::assertNotNull($doubleButeur);
        self::assertSame(
            '40 → 80 pts pour votre équipe',
            $doubleButeur['tables'][0]['rows'][1][2],
        );

        self::assertNotNull($inverseButeur);
        self::assertSame('+50', $inverseButeur['tables'][0]['rows'][0][1]);
        self::assertSame('−50', $inverseButeur['tables'][0]['rows'][0][2]);
        self::assertSame('+80', $inverseButeur['tables'][0]['rows'][1][1]);
    }
}
