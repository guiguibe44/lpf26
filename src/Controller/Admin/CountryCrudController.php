<?php

namespace App\Controller\Admin;

use App\Entity\Country;
use App\Service\UploadedImageFinalizeService;
use App\Entity\GameMatch;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class CountryCrudController extends AbstractAppCrudController
{
    public function __construct(
        private readonly UploadedImageFinalizeService $uploadedImageFinalize,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Country::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'nom',
            'groupe',
            'drapeau',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        $groupChoices = [];
        foreach (range('A', 'L') as $letter) {
            $groupChoices['Groupe '.$letter] = $letter;
        }

        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('nom'),
            ChoiceField::new('groupe', 'Groupe (poule)')
                ->setChoices($groupChoices)
                ->setRequired(false)
                ->setHelp('Lettre de poule CDM 2026 (A à L). Met à jour la phase des matchs de groupe de ce pays.'),
            ImageField::new('drapeau')
                ->setLabel('Drapeau')
                ->setBasePath('/uploads/drapeaux')
                ->setUploadDir('public/uploads/drapeaux')
                ->setRequired(false),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Country) {
            $entityInstance->setDrapeau($this->uploadedImageFinalize->finalize($entityInstance->getDrapeau(), 'drapeaux'));
            $this->syncGroupStagePhasesForCountry($entityManager, $entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Country) {
            $entityInstance->setDrapeau($this->uploadedImageFinalize->finalize($entityInstance->getDrapeau(), 'drapeaux'));
            $this->syncGroupStagePhasesForCountry($entityManager, $entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function syncGroupStagePhasesForCountry(EntityManagerInterface $entityManager, Country $country): void
    {
        $phaseLabel = $country->getGroupPhaseLabel();
        if (null === $phaseLabel || null === $country->getId()) {
            return;
        }

        $matches = $entityManager->getRepository(GameMatch::class)->createQueryBuilder('m')
            ->where('m.paysDomicile = :country OR m.paysExterieur = :country')
            ->andWhere('m.phase IS NULL OR m.phase LIKE :groupPhase OR m.phase LIKE :groupStage')
            ->setParameter('country', $country)
            ->setParameter('groupPhase', 'Group %')
            ->setParameter('groupStage', '%Group Stage%')
            ->getQuery()
            ->getResult();

        foreach ($matches as $match) {
            if ($match instanceof GameMatch) {
                $match->setPhase($phaseLabel);
            }
        }
    }

}
