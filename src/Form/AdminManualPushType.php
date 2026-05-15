<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class AdminManualPushType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'placeholder' => 'Ex. Rappel pronos — match de ce soir',
                    'maxlength' => 120,
                    'data-admin-push-preview' => 'title',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 120),
                ],
            ])
            ->add('body', TextareaType::class, [
                'label' => 'Message',
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Texte affiché dans la notification…',
                    'maxlength' => 500,
                    'data-admin-push-preview' => 'body',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 500),
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => 'Lien au clic',
                'required' => false,
                'help' => 'Optionnel. Ex. /accueil ou URL complète. Vide = ouverture de l’accueil.',
                'attr' => [
                    'placeholder' => 'https://…',
                    'data-admin-push-preview' => 'url',
                ],
                'default_protocol' => 'https',
            ]);
    }
}
