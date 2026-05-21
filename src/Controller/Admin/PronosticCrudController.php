<?php

namespace App\Controller\Admin;

use App\Entity\Pronostic;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class PronosticCrudController extends AbstractAppCrudController
{
    public static function getEntityFqcn(): string
    {
        return Pronostic::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'scoreDomicile',
            'scoreExterieur',
            'points',
            'pointsEquipe',
            'pointsBase',
            'coteCoefficient',
            'joueur.email',
            'match.id',
            'match.apiFootballFixtureId',
            'match.statut',
            'match.phase',
            'match.venueName',
            'match.paysDomicile.nom',
            'match.paysExterieur.nom',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('joueur'),
            AssociationField::new('match'),
            IntegerField::new('scoreDomicile'),
            IntegerField::new('scoreExterieur'),
            IntegerField::new('pointsBase')->setLabel('Pts base')->hideOnForm(),
            NumberField::new('coteCoefficient')->setLabel('Cote')->setNumDecimals(2)->hideOnForm(),
            NumberField::new('points')->setLabel('Pts joueur')->setNumDecimals(0)->hideOnForm(),
            NumberField::new('pointsEquipe')->setLabel('Pts équipe')->setNumDecimals(0)->hideOnForm(),
            BooleanField::new('priseRisque')->setLabel('BigBalls')->renderAsSwitch(false)->hideOnForm(),
            DateTimeField::new('createdAt')->hideOnForm(),
            DateTimeField::new('updatedAt')->hideOnForm(),
        ];
    }
}
