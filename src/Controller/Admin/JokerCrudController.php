<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Joker;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class JokerCrudController extends AbstractAppCrudController
{
    public static function getEntityFqcn(): string
    {
        return Joker::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'code',
            'name',
            'description',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('code')->setHelp('Identifiant technique (ex. double_equipe). Ne pas modifier après mise en prod.'),
            TextField::new('name')->setLabel('Nom'),
            TextareaField::new('description')->hideOnIndex(),
            ImageField::new('image')
                ->setLabel('Image')
                ->setBasePath('')
                ->hideOnForm(),
            ImageField::new('imageFilename')
                ->setLabel('Image')
                ->setBasePath('/uploads/jokers')
                ->setUploadDir('public/uploads/jokers')
                ->setRequired(false)
                ->onlyOnForms(),
            BooleanField::new('active')->setLabel('Actif'),
            IntegerField::new('sortOrder')->setLabel('Ordre'),
        ];
    }
}
