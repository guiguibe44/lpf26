<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\BadgeAward;
use App\Entity\BadgeDefinition;
use App\Service\BadgeFeature;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

class BadgeAwardCrudController extends AbstractAppCrudController
{
    public function __construct(
        private readonly BadgeFeature $badgeFeature,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return BadgeAward::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Attribution badge')
            ->setEntityLabelInPlural('Attributions badges')
            ->setDefaultSort(['awardedAt' => 'DESC'])
            ->setHelp('index', sprintf(
                'Attributions manuelles ou futures attributions automatiques. '
                .'Fonctionnalité publique %s. Renseignez joueur OU équipe selon la portée du badge.',
                $this->badgeFeature->isEnabled() ? 'activée' : 'désactivée',
            ));
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'badgeDefinition.name',
            'badgeDefinition.code',
            'user.email',
            'team.name',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('badgeDefinition', 'Badge')
            ->setRequired(true);
        yield AssociationField::new('user', 'Joueur')
            ->setHelp('Pour les badges joueur.');
        yield AssociationField::new('team', 'Équipe')
            ->setHelp('Pour les badges équipe.');
        yield DateTimeField::new('awardedAt', 'Attribué le');
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof BadgeAward) {
            $this->assertValidAward($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof BadgeAward) {
            $this->assertValidAward($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function assertValidAward(BadgeAward $award): void
    {
        if (null === $award->getUser() && null === $award->getTeam()) {
            throw new \InvalidArgumentException('Renseignez un joueur ou une équipe.');
        }

        $badge = $award->getBadgeDefinition();
        if (!$badge instanceof BadgeDefinition) {
            return;
        }

        if (!$badge->isActive()) {
            throw new \InvalidArgumentException(sprintf('Le badge « %s » est inactif.', $badge->getName()));
        }

        if ($badge->isIronic() && !$this->badgeFeature->isIronicEnabled()) {
            throw new \InvalidArgumentException('Les badges ironiques sont désactivés (BADGES_IRONIC_ENABLED=false).');
        }
    }
}
