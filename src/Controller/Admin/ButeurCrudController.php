<?php

namespace App\Controller\Admin;

use App\Entity\Buteur;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ButeurCrudController extends AbstractAppCrudController
{
    public static function getEntityFqcn(): string
    {
        return Buteur::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'prenom',
            'nom',
            'photo',
            'apiSportsPlayerId',
            'pays.nom',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('prenom'),
            TextField::new('nom'),
            AssociationField::new('pays'),
            ImageField::new('photo')
                ->setLabel('Photo')
                ->setBasePath('')
                ->setUploadDir('public/uploads/buteurs')
                ->setRequired(false),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Buteur) {
            $entityInstance->setPhoto($this->normalizeUploadPath($entityInstance->getPhoto(), '/uploads/buteurs/'));
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Buteur) {
            $entityInstance->setPhoto($this->normalizeUploadPath($entityInstance->getPhoto(), '/uploads/buteurs/'));
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
