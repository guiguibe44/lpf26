<?php

namespace App\Controller\Admin;

use App\Entity\But;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class ButCrudController extends AbstractAppCrudController
{
    public static function getEntityFqcn(): string
    {
        return But::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'minute',
            'pointsAttribues',
            'apiSportsEventKey',
            'buteur.prenom',
            'buteur.nom',
            'buteur.pays.nom',
            'matchRef.id',
            'matchRef.apiFootballFixtureId',
            'matchRef.statut',
            'matchRef.phase',
            'matchRef.venueName',
            'matchRef.referee',
            'matchRef.scoreDomicile',
            'matchRef.scoreExterieur',
            'matchRef.paysDomicile.nom',
            'matchRef.paysExterieur.nom',
        ];
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
