<?php

namespace App\Controller\Admin;

use App\Entity\Team;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TeamCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Team::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            TextField::new('slogan')->hideOnIndex(),
            ImageField::new('logo')
                ->setLabel('Logo')
                ->setBasePath('')
                ->hideOnForm(),
            ImageField::new('logoFilename')
                ->setLabel('Logo')
                ->setBasePath('/uploads/team-logos')
                ->setUploadDir('public/uploads/team-logos')
                ->setRequired(false)
                ->onlyOnForms(),
        ];
    }
}
