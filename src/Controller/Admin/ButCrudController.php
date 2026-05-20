<?php

namespace App\Controller\Admin;

use App\Entity\But;
use App\Repository\GameMatchRepository;
use App\Service\ButeurGoalScoringService;
use App\Service\TeamRankingService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class ButCrudController extends AbstractAppCrudController
{
    public function __construct(
        private readonly ButeurGoalScoringService $buteurGoalScoringService,
        private readonly GameMatchRepository $gameMatchRepository,
        private readonly TeamRankingService $teamRankingService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return But::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'minute',
            'pointsBase',
            'pointsAttribues',
            'coteCoefficient',
            'apiSportsEventKey',
            'buteur.prenom',
            'buteur.nom',
            'buteur.pays.nom',
            'matchRef.id',
            'matchRef.apiFootballFixtureId',
            'matchRef.statut',
            'matchRef.phase',
            'matchRef.venueName',
            'matchRef.referee',
            'matchRef.scoreDomicile',
            'matchRef.scoreExterieur',
            'matchRef.paysDomicile.nom',
            'matchRef.paysExterieur.nom',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('matchRef')->setLabel('Match'),
            AssociationField::new('buteur'),
            IntegerField::new('minute')->setRequired(false),
            IntegerField::new('pointsBase')->setLabel('Pts base')->hideOnForm(),
            NumberField::new('coteCoefficient')->setLabel('Cote')->setNumDecimals(2)->hideOnForm(),
            IntegerField::new('pointsAttribues')->setLabel('Points')->hideOnForm(),
            DateTimeField::new('createdAt')->hideOnForm(),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof But) {
            $this->buteurGoalScoringService->scoreBut($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
        $this->rebuildRankingIfNeeded();
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof But) {
            $this->buteurGoalScoringService->scoreBut($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
        $this->rebuildRankingIfNeeded();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::deleteEntity($entityManager, $entityInstance);
        $this->rebuildRankingIfNeeded();
    }

    private function rebuildRankingIfNeeded(): void
    {
        $latestMatch = $this->gameMatchRepository->findLatestFinishedMatch();
        if (null !== $latestMatch) {
            $this->teamRankingService->rebuildSnapshotsFromMatch($latestMatch);
        }
    }
}
