<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class AccountProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $nicknameAttrs = (bool) $options['lock_nickname']
            ? ['readonly' => true, 'class' => 'ta-form-input']
            : [];

        $builder
            ->add('nickname', TextType::class, [
                'mapped' => false,
                'label' => 'Surnom joueur',
                'attr' => $nicknameAttrs,
                'constraints' => [
                    new NotBlank(message: 'Le surnom est obligatoire.'),
                    new Length(min: 3, max: 50),
                ],
            ])
            ->add('avatarFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Avatar joueur (image)',
                'constraints' => [
                    new File(
                        maxSize: '4M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'Merci de choisir une image valide (jpg, png, webp, gif).'
                    ),
                ],
            ])
            ->add('removeAvatar', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Supprimer l\'avatar actuel',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'lock_nickname' => false,
        ]);

        $resolver->setAllowedTypes('lock_nickname', 'bool');
    }
}
