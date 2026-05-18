<?php

namespace App\Controller\Admin;

use App\Entity\Team;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TeamCrudController extends AbstractAppCrudController
{
    public static function getEntityFqcn(): string
    {
        return Team::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'name',
            'logo',
            'slogan',
            'favoriteCountry.nom',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            TextField::new('slogan')->hideOnIndex(),
            AssociationField::new('favoriteCountry', 'Équipe favorite (pays)')
                ->setHelp('Joker « Équipe favorite » : sélection nationale secrète de l’équipe.')
                ->autocomplete(),
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
