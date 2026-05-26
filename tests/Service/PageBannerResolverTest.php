<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\PageBannerResolver;
use PHPUnit\Framework\TestCase;

final class PageBannerResolverTest extends TestCase
{
    public function testResolveRandomPairFromProjectBanners(): void
    {
        $resolver = new PageBannerResolver(dirname(__DIR__, 2));
        $pair = $resolver->resolveRandomPair();

        self::assertNotNull($pair);
        self::assertStringContainsString('-light.', $pair['light']);
        self::assertStringContainsString('-dark.', $pair['dark']);
        self::assertStringStartsWith('images/banners/', $pair['light']);
        self::assertStringStartsWith('images/banners/', $pair['dark']);
    }

    public function testDiscoversAllLightDarkPairs(): void
    {
        $resolver = new PageBannerResolver(dirname(__DIR__, 2));
        $pairs = $resolver->getAvailablePairs();

        self::assertGreaterThanOrEqual(2, \count($pairs));

        $lights = array_column($pairs, 'light');
        self::assertContains('images/banners/banner-mexique-light.png', $lights);
        self::assertContains('images/banners/banner-usa-light.jpg', $lights);
    }

    public function testReturnsNullWhenDirectoryMissing(): void
    {
        $resolver = new PageBannerResolver(sys_get_temp_dir().'/lpf26-no-banners');

        self::assertNull($resolver->resolveRandomPair());
    }
}
