<?php

namespace App\Controller\Admin;

use App\Entity\TeamMember;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TeamMemberCrudController extends AbstractAppCrudController
{
    public static function getEntityFqcn(): string
    {
        return TeamMember::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'nickname',
            'team.name',
            'player.email',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('team'),
            AssociationField::new('player'),
            TextField::new('nickname'),
        ];
    }
}
