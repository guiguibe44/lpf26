<?php

declare(strict_types=1);

namespace App\Service\MainTheme;

use App\Entity\MainTheme;
use App\Enum\MainThemeBackgroundRepeat;

/**
 * Génère les variables CSS par thème (sélecteur html[data-lpf-main-theme="…"]).
 */
final class MainThemeCssRenderer
{
    /**
     * @param list<MainTheme> $themes
     */
    public function render(array $themes): string
    {
        if ([] === $themes) {
            return '';
        }

        $chunks = [];
        foreach ($themes as $theme) {
            $code = $theme->getCode();
            if (null === $code || '' === $code) {
                continue;
            }

            $escapedCode = htmlspecialchars($code, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $vars = $this->buildVariables($theme);
            if ([] === $vars) {
                continue;
            }

            $declarations = implode('; ', $vars);
            $chunks[] = sprintf('html.lpf-classic-light[data-lpf-main-theme="%s"]{%s}', $escapedCode, $declarations);
        }

        return implode("\n", $chunks);
    }

    /**
     * @return list<string>
     */
    private function buildVariables(MainTheme $theme): array
    {
        $vars = [];

        if ($theme->usesBackgroundImage()) {
            $path = $theme->getBackgroundImagePublicPath();
            if (null !== $path && '' !== $path) {
                $vars[] = '--lpf-mt-bg-image:url("'.str_replace('"', '\\"', $path).'")';
            }
            $vars[] = '--lpf-mt-bg-color:transparent';
            $vars[] = '--lpf-mt-bg-overlay:'.$this->buildOverlayLayer($theme);
        } else {
            $vars[] = '--lpf-mt-bg-image:none';
            $vars[] = '--lpf-mt-bg-overlay:none';
            $color = $theme->getBackgroundColor() ?? 'transparent';
            $vars[] = '--lpf-mt-bg-color:'.$this->safeCssColor($color);
        }

        $repeat = $this->safeCssKeyword($theme->getBackgroundRepeat(), MainThemeBackgroundRepeat::NoRepeat->value);
        $vars[] = '--lpf-mt-bg-position:'.$this->safeCssKeyword($theme->getBackgroundPosition(), 'center center');
        $vars[] = '--lpf-mt-bg-repeat:'.$repeat;
        $vars[] = '--lpf-mt-bg-size:'.$this->resolveBackgroundSize($theme, $repeat);
        $vars[] = '--lpf-mt-title:'.$this->safeCssColor((string) $theme->getTitleColor());
        $vars[] = '--lpf-mt-block-bg:'.$this->safeCssColor((string) $theme->getBlockBackgroundColor());
        $vars[] = '--lpf-mt-block-text:'.$this->safeCssColor((string) $theme->getBlockTextColor());
        $vars[] = '--lpf-mt-btn-bg:'.$this->safeCssColor((string) $theme->getButtonBackgroundColor());
        $vars[] = '--lpf-mt-btn-text:'.$this->safeCssColor((string) $theme->getButtonTextColor());

        return $vars;
    }

    private function safeCssColor(string $value): string
    {
        $value = trim($value);
        if ('transparent' === strtolower($value)) {
            return 'transparent';
        }

        if (preg_match('/^#[0-9A-Fa-f]{3,8}$/', $value)) {
            return $value;
        }

        return 'transparent';
    }

    private function safeCssKeyword(string $value, string $fallback): string
    {
        $value = trim($value);
        if ('' === $value || !preg_match('/^[a-z0-9 #%.,-]+$/i', $value)) {
            return $fallback;
        }

        return $value;
    }

    private function resolveBackgroundSize(MainTheme $theme, string $repeat): string
    {
        if (!$theme->usesBackgroundImage()) {
            return 'auto';
        }

        if (MainThemeBackgroundRepeat::Repeat->value === $repeat) {
            return 'auto';
        }

        return 'cover';
    }

    private function buildOverlayLayer(MainTheme $theme): string
    {
        if (!$theme->usesBackgroundOverlay()) {
            return 'none';
        }

        $rgba = $this->hexToRgba(
            (string) $theme->getBackgroundOverlayColor(),
            $theme->getBackgroundOverlayOpacity() / 100,
        );

        return 'linear-gradient('.$rgba.','.$rgba.')';
    }

    private function hexToRgba(string $hex, float $alpha): string
    {
        $alpha = max(0.0, min(1.0, $alpha));
        $hex = ltrim(trim($hex), '#');

        if (3 === \strlen($hex)) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (6 !== \strlen($hex) || !ctype_xdigit($hex)) {
            return sprintf('rgba(0,0,0,%s)', $this->formatAlpha($alpha));
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return sprintf('rgba(%d,%d,%d,%s)', $r, $g, $b, $this->formatAlpha($alpha));
    }

    private function formatAlpha(float $alpha): string
    {
        $formatted = rtrim(rtrim(sprintf('%.2f', $alpha), '0'), '.');

        return '' === $formatted ? '0' : $formatted;
    }
}
