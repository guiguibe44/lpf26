<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ResendTeamInvitationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('teammateEmail', EmailType::class, [
                'label' => 'E-mail du partenaire (joueur 2)',
                'help' => 'Une invitation avec lien d’inscription sera envoyée à cette adresse.',
                'constraints' => [
                    new NotBlank(message: 'L\'e-mail du partenaire est obligatoire.'),
                    new Email(message: 'Adresse e-mail invalide.'),
                ],
            ]);
    }
}
