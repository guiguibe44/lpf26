<?php

namespace App\Controller\Admin;

use App\Entity\TeamMember;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
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
        $embeddedInTeam = $this->isEmbeddedInTeamForm($pageName);

        $fields = [
            IdField::new('id')->hideOnForm(),
            TextField::new('nickname', 'Surnom'),
            AssociationField::new('player', 'Compte joueur')
                ->autocomplete()
                ->setHelp('Utilisateur Symfony lié à ce membre d’équipe.'),
            DateTimeField::new('joinedAt', 'Inscrit le')
                ->hideOnForm()
                ->setFormat('dd/MM/yyyy HH:mm'),
        ];

        if (!$embeddedInTeam) {
            array_unshift(
                $fields,
                AssociationField::new('team', 'Équipe')->autocomplete(),
            );
        }

        return $fields;
    }

    private function isEmbeddedInTeamForm(string $pageName): bool
    {
        return in_array($pageName, [
            TeamCrudController::PAGE_MEMBERS_EMBED_NEW,
            TeamCrudController::PAGE_MEMBERS_EMBED_EDIT,
        ], true);
    }
}
