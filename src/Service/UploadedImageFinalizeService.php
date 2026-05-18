<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Normalise et optimise un fichier déjà enregistré sous public/uploads/{subdir}/.
 */
final class UploadedImageFinalizeService
{
    public function __construct(
        private readonly ImageUploadOptimizer $imageUploadOptimizer,
    ) {
    }

    /**
     * @param bool $asPublicPath Si true, retourne /uploads/{subdir}/… (compte joueur) ; sinon basename seul (EasyAdmin).
     */
    public function finalize(?string $path, string $subdir, bool $asPublicPath = false): ?string
    {
        if (null === $path || '' === $path) {
            return $path;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $normalized = UploadPathHelper::normalizeStored($path, $subdir);
        if (null === $normalized || '' === $normalized) {
            return $path;
        }

        $basename = $this->imageUploadOptimizer->optimizeStoredFilename($normalized, $subdir) ?? $normalized;

        if (!$asPublicPath) {
            return $basename;
        }

        if (str_starts_with($basename, '/uploads/')) {
            return $basename;
        }

        return '/uploads/'.$subdir.'/'.$basename;
    }
}
