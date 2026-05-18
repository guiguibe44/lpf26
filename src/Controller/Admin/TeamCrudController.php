<?php

namespace App\Controller\Admin;

use App\Entity\Team;
use App\Service\UploadPathHelper;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TeamCrudController extends AbstractAppCrudController
{
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
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            TextField::new('slogan')->hideOnIndex(),
            AssociationField::new('favoriteCountry', 'Équipe favorite (pays)')
                ->setHelp('Joker « Équipe favorite » : sélection nationale secrète de l’équipe.')
                ->autocomplete(),
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
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Team) {
            $this->applyOptimizedLogoFilename($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function applyOptimizedLogoFilename(Team $team): void
    {
        $logo = $team->getLogo();
        if (null === $logo || '' === $logo) {
            return;
        }

        $basename = $this->finalizeUploadFilename(
            UploadPathHelper::normalizeStored($logo, 'team-logos') ?? basename($logo),
            'team-logos',
        );

        if (null !== $basename && '' !== $basename) {
            $team->setLogoFilename($basename);
        }
    }
}
