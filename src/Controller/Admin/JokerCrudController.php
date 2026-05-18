<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Joker;
use App\Enum\JokerTag;
use App\Service\UploadPathHelper;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class JokerCrudController extends AbstractAppCrudController
{
    public static function getEntityFqcn(): string
    {
        return Joker::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'code',
            'title',
            'name',
            'tag',
            'description',
            'technicalExplanation',
        ];
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('code')->setHelp('Identifiant technique (ex. double_equipe). Ne pas modifier après mise en prod.'),
            TextField::new('title')->setLabel('Titre')->setRequired(true),
            ChoiceField::new('tag')
                ->setLabel('Tag')
                ->setChoices(JokerTag::choices())
                ->setRequired(true)
                ->renderExpanded(false),
            TextareaField::new('description')
                ->setLabel('Description')
                ->setHelp('Texte court affiché sur la carte joker (guide, tiroir match).')
                ->hideOnIndex(),
            TextareaField::new('technicalExplanation')
                ->setLabel('Explications techniques')
                ->setHelp('Une règle par ligne (affichée en liste sur la page Jokers).')
                ->hideOnIndex(),
            ImageField::new('image')
                ->setLabel('Image')
                ->setBasePath('')
                ->hideOnForm(),
            ImageField::new('imageFilename')
                ->setLabel('Image')
                ->setBasePath('/uploads/jokers')
                ->setUploadDir('public/uploads/jokers')
                ->setRequired(false)
                ->onlyOnForms(),
            BooleanField::new('active')->setLabel('Actif'),
            IntegerField::new('sortOrder')->setLabel('Ordre'),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Joker) {
            $this->applyOptimizedImageFilename($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Joker) {
            $this->applyOptimizedImageFilename($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function applyOptimizedImageFilename(Joker $joker): void
    {
        $image = $joker->getImage();
        if (null === $image || '' === $image) {
            return;
        }

        $basename = $this->finalizeUploadFilename(
            UploadPathHelper::normalizeStored($image, 'jokers') ?? basename($image),
            'jokers',
        );

        if (null !== $basename && '' !== $basename) {
            $joker->setImageFilename($basename);
        }
    }
}
