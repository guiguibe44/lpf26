<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EditorialAuthor;
use App\Enum\EditorialAuthorCountry;
use App\Service\UploadedImageFinalizeService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class EditorialAuthorCrudController extends AbstractAppCrudController
{
    public function __construct(
        private readonly UploadedImageFinalizeService $uploadedImageFinalize,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return EditorialAuthor::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Auteur édito')
            ->setEntityLabelInPlural('Auteurs édito')
            ->setDefaultSort(['lastName' => 'ASC', 'firstName' => 'ASC']);
    }

    protected function getAdminSearchFields(): array
    {
        return ['id', 'firstName', 'lastName', 'country'];
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Identité', 'fa fa-user');
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('firstName', 'Prénom')->setRequired(true);
        yield TextField::new('lastName', 'Nom')->setRequired(true);
        yield ChoiceField::new('countryValue', 'Pays de rattachement')
            ->setChoices(EditorialAuthorCountry::choices())
            ->setRequired(true)
            ->formatValue(static function (?string $value): string {
                return EditorialAuthorCountry::tryFrom((string) $value)?->label() ?? '';
            });

        yield FormField::addFieldset('Avatar', 'fa fa-image');
        yield ImageField::new('avatar', 'Avatar')
            ->setBasePath('/uploads/'.EditorialAuthor::UPLOAD_SUBDIR)
            ->setUploadDir('public/uploads/'.EditorialAuthor::UPLOAD_SUBDIR)
            ->setRequired(false);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof EditorialAuthor) {
            $entityInstance->setAvatar(
                $this->uploadedImageFinalize->finalize($entityInstance->getAvatar(), EditorialAuthor::UPLOAD_SUBDIR),
            );
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof EditorialAuthor) {
            $entityInstance->setAvatar(
                $this->uploadedImageFinalize->finalize($entityInstance->getAvatar(), EditorialAuthor::UPLOAD_SUBDIR),
            );
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
