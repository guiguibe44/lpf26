<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Joker;
use App\Enum\JokerTag;
use App\Joker\JokerLiveStoryCasesForCode;
use App\Service\JokerLiveStoryTemplateRenderer;
use App\Service\UploadedImageFinalizeService;
use App\Service\UploadPathHelper;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class JokerCrudController extends AbstractAppCrudController
{
    public function __construct(
        private readonly UploadedImageFinalizeService $uploadedImageFinalize,
        private readonly AdminContextProviderInterface $adminContextProvider,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Joker::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setHelp(
                'index',
                'Les GIFs du récap d’équipe (plusieurs par situation, tirage aléatoire) se gèrent dans Communication → GIFs récap d’équipe.',
            );
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
            TextField::new('code')->setHelp(
                'Identifiant technique (ex. double_equipe). Ne pas modifier après mise en prod. '
                .'Les champs « Phrase — … » (live / match terminé) apparaissent à l’édition selon ce code.',
            ),
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
            ...$this->liveStoryTemplateFields($pageName),
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
            $this->finalizeJokerBeforeSave($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Joker) {
            $this->finalizeJokerBeforeSave($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function finalizeJokerBeforeSave(Joker $joker): void
    {
        $joker->pruneLiveStoryTemplatesForCode();
        $this->applyOptimizedImageFilename($joker);
    }

    /**
     * @return list<TextareaField>
     */
    private function liveStoryTemplateFields(string $pageName): array
    {
        if (Crud::PAGE_INDEX === $pageName || Crud::PAGE_DETAIL === $pageName) {
            return [];
        }

        $cases = JokerLiveStoryCasesForCode::forCode($this->resolveJokerCodeFromAdminContext());
        if ([] === $cases) {
            return [];
        }

        $fields = [];
        $first = true;

        foreach ($cases as $case) {
            $help = $case->adminHelp();
            if ($first) {
                $help .= "\n\n".JokerLiveStoryTemplateRenderer::VARIABLES_HELP;
                $first = false;
            }

            $fields[] = TextareaField::new($case->adminProperty())
                ->setLabel('Phrase — '.$case->label())
                ->setHelp($help)
                ->setFormTypeOption('attr', ['rows' => 3])
                ->hideOnIndex()
                ->setFormTypeOption('empty_data', '');
        }

        return $fields;
    }

    private function resolveJokerCodeFromAdminContext(): ?string
    {
        $context = $this->adminContextProvider->getContext();
        if (null === $context) {
            return null;
        }

        $instance = $context->getEntity()->getInstance();

        return $instance instanceof Joker ? $instance->getCode() : null;
    }

    private function applyOptimizedImageFilename(Joker $joker): void
    {
        $image = $joker->getImage();
        if (null === $image || '' === $image) {
            return;
        }

        $basename = $this->uploadedImageFinalize->finalize(
            UploadPathHelper::normalizeStored($image, 'jokers') ?? basename($image),
            'jokers',
        );

        if (null !== $basename && '' !== $basename) {
            $joker->setImageFilename($basename);
        }
    }
}
