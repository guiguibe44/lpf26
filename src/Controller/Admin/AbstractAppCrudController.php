<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\ImageUploadOptimizer;
use App\Service\UploadPathHelper;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/**
 * Pagination admin élargie + recherche multi-champs (voir {@see getAdminSearchFields} par entité).
 */
abstract class AbstractAppCrudController extends AbstractCrudController
{
    protected const ADMIN_PAGINATOR_PAGE_SIZE = 100;

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setPaginatorPageSize(static::ADMIN_PAGINATOR_PAGE_SIZE)
            ->setSearchFields($this->getAdminSearchFields());
    }

    /**
     * Propriétés Doctrine recherchables (scalaires + chemins d’associations terminant par un champ scalaire).
     *
     * @return list<string>
     */
    abstract protected function getAdminSearchFields(): array;

    protected function normalizeUploadFilename(?string $path, string $uploadSubdir): ?string
    {
        return UploadPathHelper::normalizeStored($path, $uploadSubdir);
    }

    /** Normalise le chemin stocké puis optimise le fichier sur disque. */
    protected function finalizeUploadFilename(?string $path, string $uploadSubdir): ?string
    {
        $normalized = $this->normalizeUploadFilename($path, $uploadSubdir);
        if (null === $normalized || '' === $normalized) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            return $normalized;
        }

        return $this->container->get(ImageUploadOptimizer::class)->optimizeStoredFilename($normalized, $uploadSubdir) ?? $normalized;
    }
}
