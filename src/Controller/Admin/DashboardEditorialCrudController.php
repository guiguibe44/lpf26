<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\DashboardEditorial;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;

class DashboardEditorialCrudController extends AbstractAppCrudController
{
    public static function getEntityFqcn(): string
    {
        return DashboardEditorial::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Édito dashboard')
            ->setEntityLabelInPlural('Éditos dashboard')
            ->setDefaultSort(['publishedAt' => 'DESC', 'id' => 'DESC']);
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'title',
            'content',
            'author.firstName',
            'author.lastName',
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(BooleanFilter::new('published', 'Publié'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Publication', 'fa fa-calendar');
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('title', 'Titre')->setRequired(true);
        yield AssociationField::new('author', 'Auteur')->setRequired(true);
        yield BooleanField::new('published', 'Publié');
        yield DateTimeField::new('publishedAt', 'Date de publication')
            ->setHelp('Permet de planifier une publication future.')
            ->setRequired(true);

        yield FormField::addFieldset('Contenu', 'fa fa-align-left');
        yield TextEditorField::new('content', 'Contenu')
            ->setHelp('Éditeur riche complet : titres, paragraphes, liens, listes...')
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Modifié le')->hideOnForm();
    }
}
