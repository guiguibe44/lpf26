<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SiteIntroSlide;
use App\Enum\SiteIntroVisualTheme;
use App\Service\UploadedImageFinalizeService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class SiteIntroSlideCrudController extends AbstractAppCrudController
{
    public function __construct(
        private readonly UploadedImageFinalizeService $uploadedImageFinalize,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SiteIntroSlide::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Slide présentation')
            ->setEntityLabelInPlural('Slides présentation')
            ->setDefaultSort(['sortOrder' => 'ASC', 'title' => 'ASC'])
            ->setPaginatorPageSize(50);
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'title',
            'eyebrow',
            'body',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Publication', 'fa fa-list-ol');
        yield IdField::new('id')->hideOnForm();
        yield IntegerField::new('sortOrder', 'Ordre')
            ->setHelp('Plus petit = affiché en premier dans la présentation.');
        yield BooleanField::new('active', 'Actif');
        yield TextField::new('title', 'Titre')->setRequired(true);
        yield TextField::new('eyebrow', 'Sur-titre')
            ->setHelp('Petit libellé au-dessus du titre (optionnel).')
            ->hideOnIndex();

        yield FormField::addFieldset('Contenu', 'fa fa-align-left');
        yield TextEditorField::new('body', 'Texte')
            ->setHelp('Éditeur riche : paragraphes, liens, listes, boutons HTML autorisés.')
            ->hideOnIndex();

        yield FormField::addFieldset('Zone visuelle (gauche)', 'fa fa-image');
        yield ChoiceField::new('visualThemeValue', 'Style de fond')
            ->setChoices(SiteIntroVisualTheme::choices())
            ->setHelp('Choisir « Aucun » pour afficher uniquement l’image, sans dégradé coloré derrière.')
            ->setRequired(true)
            ->renderExpanded(false)
            ->formatValue(static function (?string $value): string {
                $theme = SiteIntroVisualTheme::tryFrom((string) $value);

                return $theme?->label() ?? '';
            });
        yield TextField::new('icon', 'Icône Tabler')
            ->setHelp(
                'Si pas d’image téléversée. Saisir le nom de l’icône sans le préfixe « ti- » (ex. users, ball-football, trophy). '
                .'Liste complète : https://tabler.io/icons'
            )
            ->hideOnIndex();
        yield TextField::new('visualBadge', 'Badge visuel')
            ->setHelp('Texte court en bas à droite de la zone visuelle (ex. × cote).')
            ->hideOnIndex();
        yield ImageField::new('image', 'Image')
            ->setBasePath('/uploads/'.SiteIntroSlide::UPLOAD_SUBDIR)
            ->setUploadDir('public/uploads/'.SiteIntroSlide::UPLOAD_SUBDIR)
            ->setRequired(false);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof SiteIntroSlide) {
            $this->finalizeBeforeSave($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof SiteIntroSlide) {
            $this->finalizeBeforeSave($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function finalizeBeforeSave(SiteIntroSlide $slide): void
    {
        $slide->setImage(
            $this->uploadedImageFinalize->finalize($slide->getImage(), SiteIntroSlide::UPLOAD_SUBDIR),
        );
    }
}
