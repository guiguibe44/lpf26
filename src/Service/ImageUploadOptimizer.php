<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\UploadImageCategory;
use Psr\Log\LoggerInterface;

/**
 * Redimensionne et compresse les images locales (GD), sortie WebP si possible.
 */
final class ImageUploadOptimizer
{
    private const SKIP_EXTENSIONS = ['svg', 'gif'];

    public function __construct(
        private readonly string $projectDir,
        private readonly int $webpQuality = 82,
        private readonly int $jpegQuality = 85,
        private readonly int $pngCompression = 8,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Optimise un fichier déjà présent dans public/uploads/{subdir}/.
     *
     * @return string|null Nouveau nom de fichier (basename) ou null si inchangé / ignoré
     */
    public function optimizeStoredFilename(?string $stored, string $subdir): ?string
    {
        $basename = UploadPathHelper::normalizeStored($stored, $subdir);
        if (null === $basename || '' === $basename) {
            return $stored;
        }

        if (str_starts_with($basename, 'http://') || str_starts_with($basename, 'https://')) {
            return $stored;
        }

        $category = UploadImageCategory::tryFromSubdir($subdir);
        if (null === $category) {
            return $basename;
        }

        $absolutePath = $this->absolutePathFor($subdir, $basename);
        if (!is_file($absolutePath)) {
            return $basename;
        }

        $optimizedBasename = $this->optimizeAbsolutePath($absolutePath, $category);

        return $optimizedBasename ?? $basename;
    }

  /**
     * @return string|null basename du fichier final (peut changer d’extension)
     */
    public function optimizeAbsolutePath(string $absolutePath, UploadImageCategory $category): ?string
    {
        if (!extension_loaded('gd')) {
            return basename($absolutePath);
        }

        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (\in_array($extension, self::SKIP_EXTENSIONS, true)) {
            return basename($absolutePath);
        }

        $source = $this->loadImage($absolutePath, $extension);
        if (null === $source) {
            return basename($absolutePath);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if (false === $width || false === $height || $width < 1 || $height < 1) {
            return basename($absolutePath);
        }

        $working = $this->applyExifOrientation($source, $absolutePath);
        $width = imagesx($working);
        $height = imagesy($working);
        if (false === $width || false === $height) {
            return basename($absolutePath);
        }

        [$targetW, $targetH] = $this->computeTargetSize(
            $width,
            $height,
            $category->maxWidth(),
            $category->maxHeight(),
            $category->allowUpscale(),
        );

        if ($targetW !== $width || $targetH !== $height) {
            $resized = imagescale($working, $targetW, $targetH, IMG_BILINEAR_FIXED);
            if (false !== $resized) {
                $working = $resized;
            }
        }

        $targetBasename = $this->buildOptimizedBasename($absolutePath);
        $targetPath = dirname($absolutePath).'/'.$targetBasename;

        $saved = $this->saveImage($working, $targetPath);

        if (!$saved) {
            $this->logger?->warning('Image upload optimization failed to save.', [
                'path' => $targetPath,
            ]);

            return basename($absolutePath);
        }

        if ($targetPath !== $absolutePath && is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        return $targetBasename;
    }

    public function absolutePathFor(string $subdir, string $basename): string
    {
        return $this->projectDir.'/public/uploads/'.$subdir.'/'.ltrim($basename, '/');
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function computeTargetSize(
        int $width,
        int $height,
        int $maxWidth,
        int $maxHeight,
        bool $allowUpscale,
    ): array {
        if ($width <= $maxWidth && $height <= $maxHeight) {
            return $allowUpscale ? [$width, $height] : [$width, $height];
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);
        if (!$allowUpscale) {
            $ratio = min(1.0, $ratio);
        }

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }

  /**
     * @return \GdImage|null
     */
    private function loadImage(string $path, string $extension): ?\GdImage
    {
        return match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($path) ?: null,
            'png' => @imagecreatefrompng($path) ?: null,
            'webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            'avif' => function_exists('imagecreatefromavif') ? (@imagecreatefromavif($path) ?: null) : null,
            default => null,
        };
    }

    private function applyExifOrientation(\GdImage $image, string $sourcePath): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        if (!\in_array($extension, ['jpg', 'jpeg'], true)) {
            return $image;
        }

        try {
            $exif = @exif_read_data($sourcePath);
        } catch (\Throwable) {
            return $image;
        }

        if (!\is_array($exif) || !isset($exif['Orientation'])) {
            return $image;
        }

        $rotated = match ((int) $exif['Orientation']) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated instanceof \GdImage) {
            return $rotated;
        }

        return $image;
    }

    private function buildOptimizedBasename(string $absolutePath): string
    {
        $filename = pathinfo($absolutePath, PATHINFO_FILENAME);
        if (function_exists('imagewebp')) {
            return $filename.'.webp';
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if (\in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'avif'], true)) {
            return $filename.'.'.$extension;
        }

        return $filename.'.jpg';
    }

    private function saveImage(\GdImage $image, string $targetPath): bool
    {
        $extension = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

        if ('webp' === $extension && function_exists('imagewebp')) {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);

            return imagewebp($image, $targetPath, $this->webpQuality);
        }

        if ('png' === $extension) {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);

            return imagepng($image, $targetPath, $this->pngCompression);
        }

        $rgb = imagecreatetruecolor(imagesx($image), imagesy($image));
        if (false === $rgb) {
            return false;
        }

        $white = imagecolorallocate($rgb, 255, 255, 255);
        imagefill($rgb, 0, 0, $white);
        imagecopy($rgb, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return imagejpeg($rgb, $targetPath, $this->jpegQuality);
    }
}
