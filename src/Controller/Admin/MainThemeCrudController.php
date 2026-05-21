<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MainTheme;
use App\Enum\MainThemeBackgroundPosition;
use App\Enum\MainThemeBackgroundRepeat;
use App\Repository\MainThemeRepository;
use App\Service\MainTheme\MainThemeProvider;
use App\Service\UploadedImageFinalizeService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MainThemeCrudController extends AbstractAppCrudController
{
    public function __construct(
        private readonly UploadedImageFinalizeService $uploadedImageFinalize,
        private readonly MainThemeRepository $mainThemeRepository,
        private readonly MainThemeProvider $mainThemeProvider,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return MainTheme::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Thème de page')
            ->setEntityLabelInPlural('Thèmes de page')
            ->setDefaultSort(['sortOrder' => 'ASC', 'label' => 'ASC'])
            ->setPaginatorPageSize(50);
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'code',
            'label',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Général', 'fa fa-palette');
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('label', 'Libellé')->setRequired(true);
        yield TextField::new('code', 'Code')
            ->setHelp('Identifiant technique (ex. default, dark). Minuscules, tirets, underscores. Ne pas modifier après mise en prod.')
            ->setRequired(true);
        yield BooleanField::new('active', 'Actif');
        yield IntegerField::new('sortOrder', 'Ordre d\'affichage')
            ->setHelp('Plus petit = affiché en premier dans le sélecteur joueur.');
        yield BooleanField::new('isDefault', 'Thème par défaut')
            ->setHelp('Utilisé si aucun choix enregistré dans le navigateur. Un seul thème peut être par défaut.');

        yield FormField::addFieldset('Fond de main.ta-main', 'fa fa-image');
        yield ColorField::new('backgroundColor', 'Couleur de fond')
            ->setHelp('Ex. #f8fafc ou transparent. Ignorée si une image est téléversée.')
            ->setRequired(false);
        yield ImageField::new('backgroundImage', 'Image de fond')
            ->setBasePath('/uploads/'.MainTheme::UPLOAD_SUBDIR)
            ->setUploadDir('public/uploads/'.MainTheme::UPLOAD_SUBDIR)
            ->setRequired(false)
            ->hideOnIndex();
        yield ChoiceField::new('backgroundPosition', 'Position de l\'image')
            ->setChoices(MainThemeBackgroundPosition::choices())
            ->setRequired(true);
        yield ChoiceField::new('backgroundRepeat', 'Répétition de l\'image')
            ->setChoices(MainThemeBackgroundRepeat::choices())
            ->setRequired(true);
        yield ColorField::new('backgroundOverlayColor', 'Couleur du voile sur l\'image')
            ->setHelp('Appliquée uniquement si une image de fond est définie et l\'opacité est supérieure à 0.')
            ->setRequired(false);
        yield IntegerField::new('backgroundOverlayOpacity', 'Opacité du voile (%)')
            ->setHelp('0 = pas de voile, 100 = couleur opaque sur toute l\'image.')
            ->setFormTypeOption('attr', ['min' => 0, 'max' => 100]);

        yield FormField::addFieldset('Textes hors blocs blancs', 'fa fa-font');
        yield ColorField::new('titleColor', 'Titres et textes sur le fond')
            ->setRequired(true);

        yield FormField::addFieldset('Blocs (cartes, match-card, etc.)', 'fa fa-square');
        yield ColorField::new('blockBackgroundColor', 'Fond des blocs')
            ->setRequired(true);
        yield ColorField::new('blockTextColor', 'Texte des blocs')
            ->setRequired(true);

        yield FormField::addFieldset('Boutons dans la zone principale', 'fa fa-hand-pointer');
        yield ColorField::new('buttonBackgroundColor', 'Fond des boutons')
            ->setRequired(true);
        yield ColorField::new('buttonTextColor', 'Texte des boutons')
            ->setRequired(true);

        if (Crud::PAGE_INDEX === $pageName) {
            yield ColorField::new('titleColor', 'Titres')->onlyOnIndex();
            yield ColorField::new('blockBackgroundColor', 'Blocs')->onlyOnIndex();
        }
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof MainTheme) {
            $this->finalizeBeforeSave($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
        $this->mainThemeProvider->resetCache();
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof MainTheme) {
            $this->finalizeBeforeSave($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
        $this->mainThemeProvider->resetCache();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::deleteEntity($entityManager, $entityInstance);
        $this->mainThemeProvider->resetCache();
    }

    private function finalizeBeforeSave(MainTheme $theme): void
    {
        $theme->setBackgroundImage(
            $this->uploadedImageFinalize->finalize($theme->getBackgroundImage(), MainTheme::UPLOAD_SUBDIR),
        );

        if ($theme->isDefault()) {
            $this->mainThemeRepository->clearDefaultExcept($theme);
        }
    }
}
