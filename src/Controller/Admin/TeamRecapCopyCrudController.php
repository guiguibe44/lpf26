<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\TeamRecapCopy;
use App\Enum\TeamRecapCopyCategory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

class TeamRecapCopyCrudController extends AbstractAppCrudController
{
    public static function getEntityFqcn(): string
    {
        return TeamRecapCopy::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityLabelInSingular('Texte récap d’équipe')
            ->setEntityLabelInPlural('Textes récap d’équipe')
            ->setDefaultSort(['category' => 'ASC', 'sortOrder' => 'ASC', 'code' => 'ASC'])
            ->setPaginatorPageSize(100)
            ->setHelp('index', 'Modifiez le corps des messages envoyés dans le récap bi-quotidien. Placeholders : {nickname}, {points}, {team_name}, {total_points}, {best_nickname}, {worst_nickname}, {best_points}, {gap}, {delta_positions}, {delta_points}, {delta_positions_abs}, {period_label}, {laggard_nickname}. HTML autorisé (&lt;strong&gt;, etc.). Les GIFs (objet d’e-mail et jokers) se gèrent dans « GIFs récap d’équipe ».');
    }

    protected function getAdminSearchFields(): array
    {
        return ['code', 'adminLabel', 'body', 'conditionHint'];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('category')->setChoices($this->categoryChoices()));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::new('catalog', 'Catalogue des cas')
                ->linkToRoute('admin_team_recap_copy_catalog')
                ->createAsGlobalAction())
            ->add(Crud::PAGE_INDEX, Action::new('simulator', 'Simulateur e-mail')
                ->linkToRoute('admin_team_recap_email_simulator')
                ->setIcon('fa fa-sliders')
                ->createAsGlobalAction());
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
            ->formatValue(static fn (?TeamRecapCopyCategory $c): string => $c?->label() ?? '');
        yield TextField::new('adminLabel', 'Libellé admin')
            ->setDisabled();
        yield TextField::new('conditionHint', 'Cas d’usage')
            ->setDisabled()
            ->hideOnIndex();
        yield IntegerField::new('sortOrder', 'Ordre')
            ->setHelp('Pour les variantes d’une même catégorie (plus petit = prioritaire dans la liste).');
        yield BooleanField::new('active', 'Actif');

        yield FormField::addFieldset('Texte envoyé', 'fa fa-envelope');
        yield TextareaField::new('body', 'Contenu')
            ->setHelp('Variables entre accolades ; une variante est tirée au hasard (déterministe) par palier ou par code fixe.')
            ->setNumOfRows(5)
            ->renderAsHtml(false);
    }

    /**
     * @return array<string, TeamRecapCopyCategory>
     */
    private function categoryChoices(): array
    {
        $choices = [];
        foreach (TeamRecapCopyCategory::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        return $choices;
    }
}
