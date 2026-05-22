<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TeamJokerUsage;
use App\Service\TeamJokerService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class TeamJokerUsageCrudController extends AbstractAppCrudController
{
    public function __construct(
        private readonly TeamJokerService $teamJokerService,
    ) {
    }

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
            AssociationField::new('targetTeam')->setLabel('Équipe cible'),
            DateTimeField::new('placedAt')->setLabel('Posé le')->hideOnForm(),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof TeamJokerUsage) {
            $this->assertUsageCanBeSaved($entityInstance);
        }

        try {
            parent::persistEntity($entityManager, $entityInstance);
        } catch (UniqueConstraintViolationException $e) {
            throw new \RuntimeException($this->formatUniqueConstraintMessage($e), 0, $e);
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof TeamJokerUsage) {
            $this->assertUsageCanBeSaved($entityInstance, $entityInstance->getId());
        }

        try {
            parent::updateEntity($entityManager, $entityInstance);
        } catch (UniqueConstraintViolationException $e) {
            throw new \RuntimeException($this->formatUniqueConstraintMessage($e), 0, $e);
        }
    }

    private function assertUsageCanBeSaved(TeamJokerUsage $usage, ?int $excludeUsageId = null): void
    {
        try {
            $this->teamJokerService->validateUsageForAdmin($usage, $excludeUsageId);
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }
    }

    private function formatUniqueConstraintMessage(UniqueConstraintViolationException $exception): string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'UNIQ_TEAM_MATCH_JOKER')
            || (str_contains($message, 'team_id') && str_contains($message, 'match_id'))) {
            return 'Cette équipe a déjà un joker sur ce match (une seule utilisation par match et par équipe).';
        }

        if (str_contains($message, 'UNIQ_TEAM_JOKER')
            || (str_contains($message, 'team_id') && str_contains($message, 'joker_id'))) {
            return 'Cette équipe a déjà utilisé ce type de joker (chaque joker n\'est utilisable qu\'une fois par équipe).';
        }

        return 'Contrainte d\'unicité en base : '.$message;
    }
}
