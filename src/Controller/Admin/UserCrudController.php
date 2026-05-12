<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use Doctrine\ORM\EntityManagerInterface;

class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('email'),
            AssociationField::new('buteurChoisi')->setRequired(false),
            BooleanField::new('cotisationPayee')->setLabel('Cotisation payee'),
            ImageField::new('avatar')
                ->setLabel('Avatar')
                ->setBasePath('')
                ->setUploadDir('public/uploads/avatars')
                ->setRequired(false),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $entityInstance->setAvatar($this->normalizeUploadPath($entityInstance->getAvatar(), '/uploads/avatars/'));
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $entityInstance->setAvatar($this->normalizeUploadPath($entityInstance->getAvatar(), '/uploads/avatars/'));
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
