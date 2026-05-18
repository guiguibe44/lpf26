<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\UploadImageCategory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Enregistrement local + optimisation des images uploadées (compte joueur, etc.).
 */
final class UploadedImageStorage
{
    public function __construct(
        private readonly string $projectDir,
        private readonly SluggerInterface $slugger,
        private readonly ImageUploadOptimizer $imageUploadOptimizer,
    ) {
    }

    /**
     * @return string Chemin public /uploads/{subdir}/…
     */
    public function storeUploadedFile(UploadedFile $file, UploadImageCategory $category): string
    {
        $subdir = $category->value;
        $originalFilename = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = (string) $this->slugger->slug($originalFilename);
        if ('' === $safeFilename) {
            $safeFilename = 'image';
        }

        $extension = strtolower($file->guessExtension() ?: 'bin');
        $newFilename = sprintf('%s-%s.%s', $safeFilename, uniqid('', true), $extension);

        $uploadRoot = $this->uploadRoot($subdir);
        if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0775, true) && !is_dir($uploadRoot)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier %s.', $uploadRoot));
        }

        $file->move($uploadRoot, $newFilename);

        $optimized = $this->imageUploadOptimizer->optimizeStoredFilename($newFilename, $subdir);

        return '/uploads/'.$subdir.'/'.($optimized ?? $newFilename);
    }

    public function optimizeStored(?string $stored, string $subdir): ?string
    {
        return $this->imageUploadOptimizer->optimizeStoredFilename($stored, $subdir);
    }

    private function uploadRoot(string $subdir): string
    {
        return $this->projectDir.'/public/uploads/'.$subdir;
    }
}
