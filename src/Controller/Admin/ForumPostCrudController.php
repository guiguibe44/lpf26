<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ForumPost;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class ForumPostCrudController extends AbstractAppCrudController
{
    public static function getEntityFqcn(): string
    {
        return ForumPost::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'content',
            'author.email',
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInPlural('Messages forum')
            ->setEntityLabelInSingular('Message forum')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('author', 'Auteur'),
            AssociationField::new('parent', 'Message parent')
                ->setHelp('Laissez vide pour un message racine ; renseignez pour une réponse.'),
            TextareaField::new('content', 'Contenu (HTML)')
                ->setNumOfRows(10)
                ->renderAsHtml(),
            DateTimeField::new('createdAt', 'Publié le')->hideOnForm(),
            DateTimeField::new('updatedAt', 'Modifié le')->hideOnForm(),
        ];
    }
}
