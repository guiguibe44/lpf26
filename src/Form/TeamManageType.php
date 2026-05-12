<?php

namespace App\Form;

use App\Entity\Team;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class TeamManageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de l\'equipe',
                'disabled' => (bool) $options['lock_team_name'],
                'constraints' => [
                    new NotBlank(message: 'Le nom de l\'equipe est obligatoire.'),
                    new Length(min: 2, max: 255),
                ],
            ])
            ->add('logoFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Logo equipe (image)',
                'constraints' => [
                    new File(
                        maxSize: '4M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'],
                        mimeTypesMessage: 'Merci de choisir une image valide (jpg, png, webp, gif, svg).'
                    ),
                ],
            ])
            ->add('removeLogo', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Supprimer le logo actuel',
            ])
            ->add('slogan', TextType::class, [
                'required' => false,
                'label' => 'Slogan',
                'constraints' => [
                    new Length(max: 255),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Team::class,
            'lock_team_name' => false,
        ]);

        $resolver->setAllowedTypes('lock_team_name', 'bool');
    }
}
