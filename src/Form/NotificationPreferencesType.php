<?php

declare(strict_types=1);

namespace App\Form;

use App\Enum\NotificationPreference;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class NotificationPreferencesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (NotificationPreference::cases() as $preference) {
            $builder->add($preference->value, CheckboxType::class, [
                'label' => $preference->label(),
                'required' => false,
                'false_values' => [null, '', false, '0', 0],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
