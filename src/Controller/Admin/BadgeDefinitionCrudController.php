<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\BadgeDefinition;
use App\Enum\BadgeCategory;
use App\Enum\BadgeOutcome;
use App\Enum\BadgeScope;
use App\Service\BadgeFeature;
use App\Service\UploadedImageFinalizeService;
use App\Service\UploadPathHelper;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class BadgeDefinitionCrudController extends AbstractAppCrudController
{
    public function __construct(
        private readonly BadgeFeature $badgeFeature,
        private readonly UploadedImageFinalizeService $uploadedImageFinalize,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return BadgeDefinition::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $status = $this->badgeFeature->isEnabled()
            ? 'activée (BADGES_ENABLED=true)'
            : 'désactivée en prod (BADGES_ENABLED=false) — catalogue admin disponible';

        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Badge')
            ->setEntityLabelInPlural('Badges (catalogue)')
            ->setDefaultSort(['category' => 'ASC', 'sortOrder' => 'ASC', 'name' => 'ASC'])
            ->setHelp('index', sprintf(
                'Catalogue des badges joueurs et équipes. Fonctionnalité publique %s. '
                .'Modifiez le nom, l’image, le texte chambreur et l’activation ; le code et le critère restent stables. '
                .'Les badges ironiques peuvent être masqués via BADGES_IRONIC_ENABLED.',
                $status,
            ));
    }

    protected function getAdminSearchFields(): array
    {
        return ['code', 'name', 'criterionHint', 'flavorText'];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('category')->setChoices($this->categoryChoices()))
            ->add(ChoiceFilter::new('scope')->setChoices($this->scopeChoices()))
            ->add(BooleanFilter::new('active'))
            ->add(BooleanFilter::new('ironic'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Identification', 'fa fa-tag');
        yield TextField::new('code', 'Code technique')
            ->setDisabled()
            ->setHelp('Identifiant stable — ne pas modifier.');
        yield ChoiceField::new('category', 'Catégorie')
            ->setChoices($this->categoryChoices())
            ->setDisabled()
            ->formatValue(static fn (?BadgeCategory $c): string => $c?->label() ?? '');
        yield ChoiceField::new('scope', 'Portée')
            ->setChoices($this->scopeChoices())
            ->setDisabled()
            ->formatValue(static fn (?BadgeScope $s): string => $s?->label() ?? '');
        yield ChoiceField::new('outcome', 'Résultat joker')
            ->setChoices($this->outcomeChoices())
            ->setDisabled()
            ->formatValue(static fn (?BadgeOutcome $o): string => $o?->label() ?? '—')
            ->hideOnIndex();
        yield TextField::new('criterionHint', 'Critère d’attribution')
            ->setDisabled()
            ->hideOnIndex();
        yield IntegerField::new('sortOrder', 'Ordre');
        yield BooleanField::new('ironic', 'Ironique / chambreur')
            ->setDisabled()
            ->setHelp('Désactivable globalement via BADGES_IRONIC_ENABLED.');
        yield BooleanField::new('active', 'Actif');

        yield FormField::addFieldset('Affichage', 'fa fa-eye');
        yield TextField::new('name', 'Nom affiché')
            ->setHelp('Libellé éditable — vous pourrez affiner le nom chambreur ici.');
        yield ImageField::new('image', 'Image')
            ->setBasePath('')
            ->formatValue(static fn (?string $value, ?BadgeDefinition $badge): ?string => UploadPathHelper::publicPath($badge?->getImage(), BadgeDefinition::UPLOAD_SUBDIR))
            ->hideOnForm();
        yield ImageField::new('imageFilename', 'Image')
            ->setBasePath('/uploads/'.BadgeDefinition::UPLOAD_SUBDIR)
            ->setUploadDir('public/uploads/'.BadgeDefinition::UPLOAD_SUBDIR)
            ->setRequired(false)
            ->setHelp('PNG, JPG ou WebP recommandé (carré, max 512 px). Si absente, repli sur l’icône Tabler.')
            ->onlyOnForms();
        yield TextareaField::new('flavorText', 'Texte chambreur')
            ->setHelp('Phrase affichée à l’obtention (optionnel).')
            ->setNumOfRows(2)
            ->hideOnIndex();
        yield TextField::new('icon', 'Icône Tabler (repli)')
            ->setHelp('Classe CSS Tabler (ex. ti-trophy) si pas d’image.')
            ->hideOnIndex();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof BadgeDefinition) {
            $this->finalizeBadgeImage($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof BadgeDefinition) {
            $this->finalizeBadgeImage($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function finalizeBadgeImage(BadgeDefinition $badge): void
    {
        $image = $badge->getImage();
        if (null === $image || '' === $image) {
            return;
        }

        $basename = $this->uploadedImageFinalize->finalize(
            UploadPathHelper::normalizeStored($image, BadgeDefinition::UPLOAD_SUBDIR) ?? basename($image),
            BadgeDefinition::UPLOAD_SUBDIR,
        );

        if (null !== $basename && '' !== $basename) {
            $badge->setImageFilename($basename);
        }
    }

    /**
     * @return array<string, BadgeCategory>
     */
    private function categoryChoices(): array
    {
        $choices = [];
        foreach (BadgeCategory::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        return $choices;
    }

    /**
     * @return array<string, BadgeScope>
     */
    private function scopeChoices(): array
    {
        $choices = [];
        foreach (BadgeScope::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        return $choices;
    }

    /**
     * @return array<string, BadgeOutcome|null>
     */
    private function outcomeChoices(): array
    {
        $choices = ['—' => null];
        foreach (BadgeOutcome::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        return $choices;
    }
}
