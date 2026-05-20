<?php

namespace App\Controller\Admin;

use App\Entity\GameMatch;
use App\Service\PronosticScoringService;
use App\Service\Wc2026SyncService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class GameMatchCrudController extends AbstractAppCrudController
{
    public function __construct(
        private readonly PronosticScoringService $pronosticScoringService,
        private readonly Wc2026SyncService $wc2026SyncService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return GameMatch::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'paysDomicile.nom',
            'paysExterieur.nom',
            'statut',
            'phase',
            'venueName',
            'referee',
            'apiFootballFixtureId',
            'scoreDomicile',
            'scoreExterieur',
            'pointsScoreExact',
            'pointsBonResultat',
            'pointsMauvaisResultat',
            'coteMin',
            'coteMoyenne',
            'coteMax',
        ];
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
            BooleanField::new('isKdoMatch')->setLabel('Match KDO (cadeau)'),
            BooleanField::new('apiFootballSyncEnabled')->setLabel('Synchro API-Football'),
            IntegerField::new('apiFootballFixtureId')->setRequired(false)->onlyOnDetail(),
            IntegerField::new('liveElapsedMinute')->setLabel('Minute live')->onlyOnDetail(),
            DateTimeField::new('liveScoresFinalizedAt')->setLabel('Scores finalisés le')->onlyOnDetail(),
            DateTimeField::new('apiFootballLastSyncedAt')->setLabel('Dernière synchro API')->onlyOnDetail(),
            TextField::new('venueName')->setRequired(false),
            TextField::new('referee')->setRequired(false),
            IntegerField::new('scoreDomicile')->setRequired(false),
            IntegerField::new('scoreExterieur')->setRequired(false),
            NumberField::new('coteMin')->setLabel('Cote min')->setNumDecimals(2)->hideOnForm(),
            NumberField::new('coteMoyenne')->setLabel('Cote moy.')->setNumDecimals(2)->hideOnForm(),
            NumberField::new('coteMax')->setLabel('Cote max')->setNumDecimals(2)->hideOnForm(),
            IntegerField::new('pointsScoreExact')->setLabel('Pts exact')->setRequired(false)->onlyOnDetail(),
            IntegerField::new('pointsBonResultat')->setLabel('Pts bon 1N2')->setRequired(false)->onlyOnDetail(),
            IntegerField::new('pointsMauvaisResultat')->setLabel('Pts faux')->setRequired(false)->onlyOnDetail(),
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
            if ('FINISHED' === $entityInstance->getStatut() || 'CANCELLED' === $entityInstance->getStatut()) {
                $this->wc2026SyncService->finalizeMatchAfterFullTime($entityInstance);
            }
        }
    }
}
