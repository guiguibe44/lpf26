<?php

namespace App\Controller\Admin;

use App\Entity\Buteur;
use App\Repository\UserRepository;
use App\Service\ButeurGoalScoringService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ButeurCrudController extends AbstractAppCrudController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ButeurGoalScoringService $buteurGoalScoringService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Buteur::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'prenom',
            'nom',
            'photo',
            'apiSportsPlayerId',
            'pays.nom',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('prenom'),
            TextField::new('nom'),
            AssociationField::new('pays'),
            IntegerField::new('apiSportsPlayerId', 'Sélections')
                ->onlyOnIndex()
                ->setSortable(false)
                ->formatValue(function (mixed $value, ?Buteur $buteur): string {
                    if (!$buteur instanceof Buteur || null === $buteur->getId()) {
                        return '—';
                    }

                    return (string) $this->userRepository->countWithButeurChoisiId((int) $buteur->getId());
                }),
            TextField::new('photo', 'Cote actuelle')
                ->onlyOnIndex()
                ->setSortable(false)
                ->formatValue(function (mixed $value, ?Buteur $buteur): string {
                    if (!$buteur instanceof Buteur) {
                        return '—';
                    }

                    return '×'.number_format($this->buteurGoalScoringService->getCurrentCoefficientForButeur($buteur), 2, ',', ' ');
                }),
            IntegerField::new('apiSportsPlayerId')->setLabel('ID API-Sports')->onlyOnDetail(),
            ImageField::new('photo')
                ->setLabel('Photo')
                ->setBasePath('/uploads/buteurs')
                ->setUploadDir('public/uploads/buteurs')
                ->setRequired(false),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Buteur) {
            $entityInstance->setPhoto($this->finalizeUploadFilename($entityInstance->getPhoto(), 'buteurs'));
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Buteur) {
            $entityInstance->setPhoto($this->finalizeUploadFilename($entityInstance->getPhoto(), 'buteurs'));
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

}
