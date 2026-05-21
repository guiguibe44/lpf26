<?php

declare(strict_types=1);

namespace App\Service\MainTheme;

use App\Entity\MainTheme;
use App\Repository\MainThemeRepository;

/**
 * Thèmes actifs pour le front (switcher + CSS dynamique).
 */
final class MainThemeProvider
{
    /** @var list<MainTheme>|null */
    private ?array $activeCache = null;

    public function __construct(
        private readonly MainThemeRepository $repository,
        private readonly MainThemeCssRenderer $cssRenderer,
    ) {
    }

    /**
     * @return list<MainTheme>
     */
    public function getActiveThemes(): array
    {
        if (null === $this->activeCache) {
            $this->activeCache = $this->repository->findActiveOrdered();
        }

        return $this->activeCache;
    }

    /**
     * @return list<string>
     */
    public function getActiveCodes(): array
    {
        return array_values(array_filter(array_map(
            static fn (MainTheme $t): ?string => $t->getCode(),
            $this->getActiveThemes(),
        )));
    }

    public function getDefaultCode(): string
    {
        $default = $this->repository->findDefault();
        $code = $default?->getCode();
        if (null !== $code && '' !== $code && \in_array($code, $this->getActiveCodes(), true)) {
            return $code;
        }

        $codes = $this->getActiveCodes();

        return $codes[0] ?? 'default';
    }

    public function getDynamicCss(): string
    {
        return $this->cssRenderer->render($this->getActiveThemes());
    }

    public function resetCache(): void
    {
        $this->activeCache = null;
    }
}
