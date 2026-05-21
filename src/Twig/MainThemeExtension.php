<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\MainTheme\MainThemeProvider;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class MainThemeExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly MainThemeProvider $mainThemeProvider,
    ) {
    }

    public function getGlobals(): array
    {
        $themes = $this->mainThemeProvider->getActiveThemes();

        return [
            'lpf_main_themes' => $themes,
            'lpf_main_theme_codes' => $this->mainThemeProvider->getActiveCodes(),
            'lpf_main_theme_default_code' => $this->mainThemeProvider->getDefaultCode(),
            'lpf_main_themes_css' => $this->mainThemeProvider->getDynamicCss(),
        ];
    }
}
