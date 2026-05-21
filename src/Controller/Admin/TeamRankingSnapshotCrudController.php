<?php

namespace App\Controller\Admin;

use App\Entity\TeamRankingSnapshot;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class TeamRankingSnapshotCrudController extends AbstractAppCrudController
{
    public static function getEntityFqcn(): string
    {
        return TeamRankingSnapshot::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'position',
            'totalPoints',
            'scoresExacts',
            'bonsResultats',
            'prisesRisque',
            'prisesRisqueReussies',
            'resultatsFaux',
            'team.name',
            'matchRef.id',
            'matchRef.apiFootballFixtureId',
            'matchRef.phase',
            'matchRef.statut',
            'matchRef.paysDomicile.nom',
            'matchRef.paysExterieur.nom',
        ];
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
            IntegerField::new('prisesRisque')->setLabel('Prises de risque tentees (matchs)'),
            IntegerField::new('prisesRisqueReussies')->setLabel('Prises de risque reussies (matchs)'),
            DateTimeField::new('createdAt')->hideOnForm(),
        ];
    }
}
