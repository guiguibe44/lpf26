<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TeamRecapGif;
use App\Repository\JokerRepository;
use App\Service\UploadedImageFinalizeService;
use App\Service\UploadPathHelper;
use App\TeamRecap\TeamRecapGifSlot;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class TeamRecapGifCrudController extends AbstractAppCrudController
{
    public function __construct(
        private readonly JokerRepository $jokerRepository,
        private readonly UploadedImageFinalizeService $uploadedImageFinalize,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return TeamRecapGif::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('GIF récap d’équipe')
            ->setEntityLabelInPlural('GIFs récap d’équipe')
            ->setDefaultSort(['slot' => 'ASC', 'sortOrder' => 'ASC', 'id' => 'ASC'])
            ->setHelp(
                'index',
                'Déposez plusieurs GIFs pour une même situation : un seul est choisi au hasard dans l’e-mail. '
                .'Slots « Objet » = période sans joker posé ; slots « Joker » = selon utile / pas utile.',
            );
    }

    protected function getAdminSearchFields(): array
    {
        return ['id', 'slot', 'path'];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(
            ChoiceFilter::new('slot')
                ->setChoices($this->slotChoices())
                ->canSelectMultiple(),
        );
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield ChoiceField::new('slot', 'Situation')
            ->setChoices($this->slotChoices())
            ->setHelp('Objet d’e-mail (3 paliers de points) ou joker (utile / pas utile).')
            ->formatValue(static fn (?string $slot): string => null !== $slot && '' !== $slot
                ? TeamRecapGifSlot::adminLabelFor($slot)
                : '');
        yield ImageField::new('path')
            ->setLabel('GIF')
            ->setBasePath('')
            ->hideOnForm();
        yield ImageField::new('pathFilename')
            ->setLabel('GIF')
            ->setBasePath('/uploads/'.TeamRecapGif::UPLOAD_SUBDIR)
            ->setUploadDir('public/uploads/'.TeamRecapGif::UPLOAD_SUBDIR)
            ->setRequired($pageName === Crud::PAGE_NEW)
            ->onlyOnForms();
        yield IntegerField::new('sortOrder', 'Ordre')
            ->setHelp('Ordre d’affichage admin uniquement ; le tirage en e-mail est aléatoire.');
        yield BooleanField::new('active', 'Actif');
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof TeamRecapGif) {
            $this->finalizeBeforeSave($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof TeamRecapGif) {
            $this->finalizeBeforeSave($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function finalizeBeforeSave(TeamRecapGif $gif): void
    {
        $stored = $gif->getPath();
        if (null === $stored || '' === $stored) {
            return;
        }

        $basename = $this->uploadedImageFinalize->finalize(
            UploadPathHelper::normalizeStored($stored, TeamRecapGif::UPLOAD_SUBDIR) ?? basename($stored),
            TeamRecapGif::UPLOAD_SUBDIR,
        );

        if (null !== $basename && '' !== $basename) {
            $gif->setPathFilename($basename);
        }
    }

    /**
     * @return array<string, string>
     */
    private function slotChoices(): array
    {
        return TeamRecapGifSlot::adminChoiceList($this->jokerRepository->findAllOrdered());
    }
}
