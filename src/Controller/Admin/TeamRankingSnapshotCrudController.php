<?php

namespace App\Controller\Admin;

use App\Entity\TeamRankingSnapshot;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class TeamRankingSnapshotCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TeamRankingSnapshot::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('matchRef')->setLabel('Match'),
            AssociationField::new('team')->setLabel('Equipe'),
            IntegerField::new('position')->setLabel('Position'),
            NumberField::new('totalPoints')->setNumDecimals(0)->setLabel('Total points equipe'),
            IntegerField::new('scoresExacts')->setLabel('Total scores exacts'),
            IntegerField::new('bonsResultats')->setLabel('Total bons resultats'),
            IntegerField::new('resultatsFaux')->setLabel('Total resultats faux'),
            IntegerField::new('prisesRisque')->setLabel('Total prises de risques'),
            DateTimeField::new('createdAt')->hideOnForm(),
        ];
    }
}
