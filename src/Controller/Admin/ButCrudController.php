<?php

namespace App\Controller\Admin;

use App\Entity\But;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class ButCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return But::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('matchRef')->setLabel('Match'),
            AssociationField::new('buteur'),
            IntegerField::new('minute')->setRequired(false),
            IntegerField::new('pointsAttribues')->setLabel('Points'),
            DateTimeField::new('createdAt')->hideOnForm(),
        ];
    }
}
