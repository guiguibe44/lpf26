<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TeamJokerUsage;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class TeamJokerUsageCrudController extends AbstractAppCrudController
{
    public static function getEntityFqcn(): string
    {
        return TeamJokerUsage::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'team.name',
            'joker.name',
            'joker.code',
            'match.id',
            'match.paysDomicile.nom',
            'match.paysExterieur.nom',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('team')->setLabel('Équipe'),
            AssociationField::new('joker')->setLabel('Joker'),
            AssociationField::new('match')->setLabel('Match'),
            DateTimeField::new('placedAt')->setLabel('Posé le')->hideOnForm(),
        ];
    }
}
