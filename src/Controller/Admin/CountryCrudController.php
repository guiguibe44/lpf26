<?php

namespace App\Controller\Admin;

use App\Entity\Country;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CountryCrudController extends AbstractAppCrudController
{
    public static function getEntityFqcn(): string
    {
        return Country::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'nom',
            'drapeau',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('nom'),
            ImageField::new('drapeau')
                ->setLabel('Drapeau')
                ->setBasePath('')
                ->setUploadDir('public/uploads/drapeaux')
                ->setRequired(false),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Country) {
            $entityInstance->setDrapeau($this->normalizeUploadPath($entityInstance->getDrapeau(), '/uploads/drapeaux/'));
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Country) {
            $entityInstance->setDrapeau($this->normalizeUploadPath($entityInstance->getDrapeau(), '/uploads/drapeaux/'));
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function normalizeUploadPath(?string $path, string $prefix): ?string
    {
        if (null === $path || '' === $path || str_starts_with($path, '/uploads/')) {
            return $path;
        }

        return $prefix.ltrim($path, '/');
    }
}
