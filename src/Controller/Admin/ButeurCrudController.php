<?php

namespace App\Controller\Admin;

use App\Entity\Buteur;
use App\Repository\UserRepository;
use App\Service\ButeurGoalScoringService;
use App\Service\UploadedImageFinalizeService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ButeurCrudController extends AbstractAppCrudController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ButeurGoalScoringService $buteurGoalScoringService,
        private readonly UploadedImageFinalizeService $uploadedImageFinalize,
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
            'position',
            'numero',
            'photo',
            'apiSportsPlayerId',
            'pays.nom',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield BooleanField::new('actif', 'Actif')
            ->setHelp('Désactivé : masqué des effectifs, du terrain et du choix buteur.')
            ->renderAsSwitch();

        yield TextField::new('prenom', 'Prénom');
        yield TextField::new('nom', 'Nom');
        yield AssociationField::new('pays', 'Pays');

        yield TextField::new('position', 'Poste')
            ->setHelp('Ex. Goalkeeper, Defender, Midfielder, Attacker — utilisé pour la compo terrain.')
            ->hideOnIndex();

        yield IntegerField::new('numero', 'N°')
            ->setHelp('Numéro de maillot en sélection.');

        yield TextField::new('position', 'Poste')
            ->onlyOnIndex();

        yield IntegerField::new('apiSportsPlayerId', 'Choix joueurs')
            ->onlyOnIndex()
            ->setSortable(false)
            ->formatValue(function (mixed $value, ?Buteur $buteur): string {
                if (!$buteur instanceof Buteur || null === $buteur->getId()) {
                    return '—';
                }

                return (string) $this->userRepository->countWithButeurChoisiId((int) $buteur->getId());
            });

        yield TextField::new('photo', 'Pts/but')
            ->onlyOnIndex()
            ->setSortable(false)
            ->formatValue(function (mixed $value, ?Buteur $buteur): string {
                if (!$buteur instanceof Buteur) {
                    return '—';
                }

                return $this->buteurGoalScoringService->getPointsPerGoalForButeur($buteur).' pts';
            });

        yield IntegerField::new('apiSportsPlayerId', 'ID API-Sports')
            ->setHelp('Identifiant joueur API-Football (synchro). Laisser vide pour un ajout manuel.')
            ->hideOnIndex();

        yield ImageField::new('photo', 'Photo')
            ->setBasePath('/uploads/buteurs')
            ->setUploadDir('public/uploads/buteurs')
            ->setRequired(false);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Buteur) {
            $entityInstance->setPhoto($this->uploadedImageFinalize->finalize($entityInstance->getPhoto(), 'buteurs'));
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Buteur) {
            $entityInstance->setPhoto($this->uploadedImageFinalize->finalize($entityInstance->getPhoto(), 'buteurs'));
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
