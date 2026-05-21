<?php

declare(strict_types=1);

namespace App\Tests\Service\MainTheme;

use App\Entity\MainTheme;
use App\Service\MainTheme\MainThemeCssRenderer;
use PHPUnit\Framework\TestCase;

final class MainThemeCssRendererTest extends TestCase
{
    public function testRenderColorTheme(): void
    {
        $theme = (new MainTheme())
            ->setCode('dark')
            ->setLabel('Sombre')
            ->setTitleColor('#f8fafc')
            ->setBlockBackgroundColor('#ffffff')
            ->setBlockTextColor('#0f172a')
            ->setButtonBackgroundColor('#16a34a')
            ->setButtonTextColor('#ffffff')
            ->setBackgroundColor('#17171c');

        $css = (new MainThemeCssRenderer())->render([$theme]);

        self::assertStringContainsString('data-lpf-main-theme="dark"', $css);
        self::assertStringContainsString('--lpf-mt-bg-color:#17171c', $css);
        self::assertStringContainsString('--lpf-mt-bg-image:none', $css);
        self::assertStringContainsString('--lpf-mt-title:#f8fafc', $css);
    }

    public function testRenderImageTheme(): void
    {
        $theme = (new MainTheme())
            ->setCode('stadium')
            ->setLabel('Stade')
            ->setTitleColor('#ffffff')
            ->setBlockBackgroundColor('#ffffff')
            ->setBlockTextColor('#111111')
            ->setButtonBackgroundColor('#e82030')
            ->setButtonTextColor('#ffffff')
            ->setBackgroundImage('bg-stade.jpg')
            ->setBackgroundPosition('top center')
            ->setBackgroundRepeat('no-repeat');

        $css = (new MainThemeCssRenderer())->render([$theme]);

        self::assertStringContainsString('--lpf-mt-bg-image:url("/uploads/main-themes/bg-stade.jpg")', $css);
        self::assertStringContainsString('--lpf-mt-bg-position:top center', $css);
        self::assertStringContainsString('--lpf-mt-bg-repeat:no-repeat', $css);
        self::assertStringContainsString('--lpf-mt-bg-size:cover', $css);
        self::assertStringContainsString('--lpf-mt-bg-overlay:none', $css);
    }

    public function testRenderImageThemeWithOverlay(): void
    {
        $theme = (new MainTheme())
            ->setCode('stadium')
            ->setLabel('Stade')
            ->setTitleColor('#ffffff')
            ->setBlockBackgroundColor('#ffffff')
            ->setBlockTextColor('#111111')
            ->setButtonBackgroundColor('#e82030')
            ->setButtonTextColor('#ffffff')
            ->setBackgroundImage('bg-stade.jpg')
            ->setBackgroundOverlayColor('#17171c')
            ->setBackgroundOverlayOpacity(45);

        $css = (new MainThemeCssRenderer())->render([$theme]);

        self::assertStringContainsString('--lpf-mt-bg-overlay:linear-gradient(rgba(23,23,28,0.45),rgba(23,23,28,0.45))', $css);
    }

    public function testRenderImageThemeWithRepeatUsesAutoSize(): void
    {
        $theme = (new MainTheme())
            ->setCode('pattern')
            ->setLabel('Motif')
            ->setTitleColor('#ffffff')
            ->setBlockBackgroundColor('#ffffff')
            ->setBlockTextColor('#111111')
            ->setButtonBackgroundColor('#e82030')
            ->setButtonTextColor('#ffffff')
            ->setBackgroundImage('pattern.png')
            ->setBackgroundRepeat('repeat');

        $css = (new MainThemeCssRenderer())->render([$theme]);

        self::assertStringContainsString('--lpf-mt-bg-repeat:repeat', $css);
        self::assertStringContainsString('--lpf-mt-bg-size:auto', $css);
    }
}
