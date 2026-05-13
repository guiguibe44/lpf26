<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email du joueur 1',
                'constraints' => [
                    new NotBlank(message: 'L\'email est obligatoire.'),
                    new Email(message: 'Adresse email invalide.'),
                ],
            ])
            ->add('teamName', TextType::class, [
                'mapped' => false,
                'label' => 'Nom de l\'equipe',
                'constraints' => [
                    new NotBlank(message: 'Le nom de l\'equipe est obligatoire.'),
                    new Length(min: 2, max: 255),
                ],
            ])
            ->add('nickname', TextType::class, [
                'mapped' => false,
                'label' => 'Votre surnom (joueur 1)',
                'constraints' => [
                    new NotBlank(message: 'Le surnom est obligatoire.'),
                    new Length(min: 3, max: 50),
                ],
            ])
            ->add('teammateEmail', EmailType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Email du joueur 2',
                'help' => 'Optionnel, invitation immediate.',
                'constraints' => [
                    new Email(message: 'Adresse email invalide.'),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'label' => 'Mot de passe',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir un mot de passe.',
                    ),
                    new Length(
                        min: 8,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        max: 4096,
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
