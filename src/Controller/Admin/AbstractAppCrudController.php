<?php

declare(strict_types=1);

namespace App\Controller\Admin;

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
}
