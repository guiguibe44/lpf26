<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class CreateTeamInvitationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('teamName', TextType::class, [
                'label' => 'Nom de l\'équipe',
                'constraints' => [
                    new NotBlank(message: 'Le nom de l\'équipe est obligatoire.'),
                    new Length(
                        min: 1,
                        max: 255,
                        minMessage: 'Le nom de l\'équipe doit contenir au moins {{ limit }} caractère.',
                        maxMessage: 'Le nom de l\'équipe ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('teammateEmail', EmailType::class, [
                'label' => 'E-mail du partenaire (joueur 2)',
                'help' => 'Une invitation avec lien d\'inscription sera envoyée à cette adresse.',
                'constraints' => [
                    new NotBlank(message: 'L\'e-mail du partenaire est obligatoire.'),
                    new Email(message: 'Adresse e-mail invalide.'),
                ],
            ]);
    }
}
