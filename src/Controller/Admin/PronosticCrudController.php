<?php

namespace App\Controller\Admin;

use App\Entity\Pronostic;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class PronosticCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Pronostic::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('joueur'),
            AssociationField::new('match'),
            IntegerField::new('scoreDomicile'),
            IntegerField::new('scoreExterieur'),
            IntegerField::new('pointsBase')->setRequired(false)->hideOnForm(),
            NumberField::new('coteCoefficient')->setLabel('Cote')->setNumDecimals(2)->setRequired(false)->hideOnForm(),
            BooleanField::new('priseRisque')->setLabel('Prise de risque')->renderAsSwitch(false)->hideOnForm(),
            NumberField::new('points')->setNumDecimals(0)->setRequired(false)->hideOnForm(),
            DateTimeField::new('createdAt')->hideOnForm(),
            DateTimeField::new('updatedAt')->hideOnForm(),
        ];
    }
}
