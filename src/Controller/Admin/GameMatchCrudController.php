<?php

namespace App\Controller\Admin;

use App\Entity\GameMatch;
use App\Service\PronosticScoringService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class GameMatchCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly PronosticScoringService $pronosticScoringService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return GameMatch::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('paysDomicile'),
            AssociationField::new('paysExterieur'),
            DateTimeField::new('dateHeure'),
            ChoiceField::new('statut')->setChoices([
                'Programme' => 'SCHEDULED',
                'En cours' => 'LIVE',
                'Termine' => 'FINISHED',
                'Reporte' => 'POSTPONED',
                'Annule' => 'CANCELLED',
            ]),
            TextField::new('phase')->setRequired(false),
            IntegerField::new('apiFootballFixtureId')->setRequired(false)->onlyOnDetail(),
            TextField::new('venueName')->setRequired(false),
            TextField::new('referee')->setRequired(false),
            IntegerField::new('scoreDomicile')->setRequired(false),
            IntegerField::new('scoreExterieur')->setRequired(false),
            IntegerField::new('pointsScoreExact')->setRequired(false)->hideOnIndex(),
            IntegerField::new('pointsBonResultat')->setRequired(false)->hideOnIndex(),
            IntegerField::new('pointsMauvaisResultat')->setRequired(false)->hideOnIndex(),
            NumberField::new('coteMin')->setNumDecimals(2)->setRequired(false)->hideOnForm(),
            NumberField::new('coteMoyenne')->setNumDecimals(2)->setRequired(false)->hideOnForm(),
            NumberField::new('coteMax')->setNumDecimals(2)->setRequired(false)->hideOnForm(),
            DateTimeField::new('createdAt')->hideOnForm(),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);

        if ($entityInstance instanceof GameMatch) {
            $this->pronosticScoringService->rescoreForMatch($entityInstance);
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::updateEntity($entityManager, $entityInstance);

        if ($entityInstance instanceof GameMatch) {
            $this->pronosticScoringService->rescoreForMatch($entityInstance);
        }
    }
}
