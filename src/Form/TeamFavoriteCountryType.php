<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Country;
use App\Entity\Team;
use App\Repository\CountryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TeamFavoriteCountryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('favoriteCountry', EntityType::class, [
            'class' => Country::class,
            'label' => 'Sélection nationale favorite',
            'choice_label' => 'nom',
            'placeholder' => 'Choisir un pays…',
            'required' => false,
            'disabled' => (bool) $options['disabled'],
            'query_builder' => static fn (CountryRepository $repository) => $repository->createQueryBuilder('c')
                ->orderBy('c.nom', 'ASC'),
            'choice_attr' => static function (?Country $country): array {
                if (!$country instanceof Country) {
                    return [];
                }

                $attrs = [
                    'data-country-initial' => mb_strtoupper(mb_substr((string) $country->getNom(), 0, 1)),
                ];
                $flagPath = $country->getDrapeauPublicPath();
                if (null !== $flagPath && '' !== $flagPath) {
                    $attrs['data-flag-src'] = $flagPath;
                }

                return $attrs;
            },
            'help' => 'Choix secret : les autres équipes ne voient pas votre sélection. Modifiable jusqu’au coup d’envoi de la compétition.',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Team::class,
            'disabled' => false,
        ]);

        $resolver->setAllowedTypes('disabled', 'bool');
    }
}
