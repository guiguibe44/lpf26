<?php

namespace App\Controller\Admin;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Service\UploadedImageFinalizeService;
use App\Service\UploadPathHelper;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TeamCrudController extends AbstractAppCrudController
{
    public const PAGE_MEMBERS_EMBED_NEW = 'embed_team_members_new';

    public const PAGE_MEMBERS_EMBED_EDIT = 'embed_team_members_edit';
    public function __construct(
        private readonly UploadedImageFinalizeService $uploadedImageFinalize,
    ) {
    }

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
            'members.nickname',
            'members.player.email',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        $membersField = CollectionField::new('members', 'Joueurs')
            ->useEntryCrudForm(
                TeamMemberCrudController::class,
                self::PAGE_MEMBERS_EMBED_NEW,
                self::PAGE_MEMBERS_EMBED_EDIT,
            )
            ->setEntryIsComplex()
            ->setEntryToStringMethod(static function (?TeamMember $member): string {
                if (!$member instanceof TeamMember) {
                    return 'Nouveau joueur';
                }

                $nickname = trim((string) $member->getNickname());
                $email = $member->getPlayer()?->getEmail() ?? '—';

                return '' !== $nickname ? sprintf('%s (%s)', $nickname, $email) : $email;
            })
            ->setHelp('Maximum 2 joueurs par équipe. Modifier le surnom ou rattacher un autre compte utilisateur.')
            ->hideOnIndex();

        if (Crud::PAGE_EDIT === $pageName) {
            $membersField = $membersField
                ->renderExpanded()
                ->allowAdd()
                ->allowDelete();
        } else {
            $membersField = $membersField
                ->allowAdd(false)
                ->allowDelete(false);
        }

        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            TextField::new('membresResume', 'Joueurs')->hideOnForm(),
            TextField::new('slogan')->hideOnIndex(),
            AssociationField::new('favoriteCountry', 'Équipe favorite (pays)')
                ->setHelp('Joker « Équipe favorite » : sélection nationale secrète de l’équipe.')
                ->autocomplete(),
            $membersField,
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

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Team) {
            $this->applyOptimizedLogoFilename($entityInstance);
            $this->syncMembersTeamRelation($entityInstance);
            $this->assertValidMembersCount($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Team) {
            $this->applyOptimizedLogoFilename($entityInstance);
            $this->syncMembersTeamRelation($entityInstance);
            $this->assertValidMembersCount($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function syncMembersTeamRelation(Team $team): void
    {
        foreach ($team->getMembers() as $member) {
            if ($member->getTeam() !== $team) {
                $team->addMember($member);
            }
        }
    }

    private function assertValidMembersCount(Team $team): void
    {
        if ($team->getMembers()->count() > 2) {
            throw new \InvalidArgumentException('Une équipe ne peut pas comporter plus de 2 joueurs.');
        }
    }

    private function applyOptimizedLogoFilename(Team $team): void
    {
        $logo = $team->getLogo();
        if (null === $logo || '' === $logo) {
            return;
        }

        $basename = $this->uploadedImageFinalize->finalize(
            UploadPathHelper::normalizeStored($logo, 'team-logos') ?? basename($logo),
            'team-logos',
        );

        if (null !== $basename && '' !== $basename) {
            $team->setLogoFilename($basename);
        }
    }
}
