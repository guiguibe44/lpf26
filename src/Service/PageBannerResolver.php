<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Paire d'images bannière (suffixes -light / -dark) dans public/images/banners/.
 */
final class PageBannerResolver
{
    private const string BANNER_DIR = 'images/banners';
    private const string SUFFIX_LIGHT = '-light';
    private const string SUFFIX_DARK = '-dark';

    /** @var list<string> */
    private const array EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{light: string, dark: string}|null chemins relatifs à public/
     */
    /**
     * @return list<array{light: string, dark: string}>
     */
    public function getAvailablePairs(): array
    {
        $pairs = $this->discoverPairs();
        usort(
            $pairs,
            static fn (array $a, array $b): int => strcmp($a['light'], $b['light']),
        );

        return $pairs;
    }

    public function resolveRandomPair(): ?array
    {
        $pairs = $this->getAvailablePairs();
        if ([] === $pairs) {
            return null;
        }

        return $pairs[random_int(0, \count($pairs) - 1)];
    }

    /**
     * @return list<array{light: string, dark: string}>
     */
    private function discoverPairs(): array
    {
        $dir = $this->projectDir.'/public/'.self::BANNER_DIR;
        if (!is_dir($dir)) {
            return [];
        }

        $pairs = [];
        $entries = scandir($dir);
        if (false === $entries) {
            return [];
        }

        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir.'/'.$entry;
            if (!is_file($path)) {
                continue;
            }

            $basename = pathinfo($entry, PATHINFO_FILENAME);
            $extension = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($extension, self::EXTENSIONS, true)) {
                continue;
            }

            if (!str_ends_with($basename, self::SUFFIX_LIGHT)) {
                continue;
            }

            $stem = substr($basename, 0, -strlen(self::SUFFIX_LIGHT));
            $darkRelative = $this->findVariantPath($dir, $stem.self::SUFFIX_DARK);
            if (null === $darkRelative) {
                continue;
            }

            $pairs[] = [
                'light' => self::BANNER_DIR.'/'.$basename.'.'.$extension,
                'dark' => $darkRelative,
            ];
        }

        return $pairs;
    }

    private function findVariantPath(string $dir, string $basename): ?string
    {
        foreach (self::EXTENSIONS as $extension) {
            $candidate = $dir.'/'.$basename.'.'.$extension;
            if (is_file($candidate)) {
                return self::BANNER_DIR.'/'.$basename.'.'.$extension;
            }
        }

        return null;
    }
}
