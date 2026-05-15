<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\AdminRecipientScope;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class AdminManualMessageType extends AbstractType
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sendPush', CheckboxType::class, [
                'label' => 'Notification push',
                'required' => false,
                'false_values' => [null, '', '0', 'false', false],
                'data' => true,
            ])
            ->add('sendEmail', CheckboxType::class, [
                'label' => 'E-mail',
                'required' => false,
                'false_values' => [null, '', '0', 'false', false],
                'data' => false,
            ])
            ->add('recipientScope', EnumType::class, [
                'class' => AdminRecipientScope::class,
                'label' => 'Destinataires',
                'choice_label' => static fn (AdminRecipientScope $scope): string => $scope->label(),
                'choices' => [
                    AdminRecipientScope::All,
                    AdminRecipientScope::Selection,
                ],
                'data' => AdminRecipientScope::All,
                'expanded' => true,
            ])
            ->add('players', EntityType::class, [
                'class' => User::class,
                'choices' => $this->userRepository->findActivePlayersOrderedByEmail(),
                'choice_label' => static fn (User $user): string => (string) $user->getEmail(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'empty_data' => [],
                'label' => 'Joueurs sélectionnés',
                'help' => 'Cochez un ou plusieurs joueurs.',
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'maxlength' => 120,
                    'data-admin-push-preview' => 'title',
                ],
                'constraints' => [new NotBlank(), new Length(max: 120)],
            ])
            ->add('body', TextareaType::class, [
                'label' => 'Message',
                'attr' => [
                    'rows' => 5,
                    'maxlength' => 2000,
                    'data-admin-push-preview' => 'body',
                ],
                'constraints' => [new NotBlank(), new Length(max: 2000)],
            ])
            ->add('url', TextType::class, [
                'label' => 'Lien (push)',
                'required' => false,
                'help' => 'Optionnel. Ex. /matchs ou https://… (push uniquement).',
                'attr' => [
                    'placeholder' => '/matchs',
                    'data-admin-push-preview' => 'url',
                ],
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!\is_array($data)) {
                return;
            }

            if (($data['recipientScope'] ?? null) === AdminRecipientScope::All->value) {
                $data['players'] = [];
            } elseif (!isset($data['players']) || !\is_array($data['players'])) {
                $data['players'] = [];
            }

            $event->setData($data);
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $form = $event->getForm();
            $sendPush = (bool) $form->get('sendPush')->getData();
            $sendEmail = (bool) $form->get('sendEmail')->getData();

            if (!$sendPush && !$sendEmail) {
                $form->addError(new FormError('Choisissez au moins un canal : push ou e-mail.'));
            }

            $scope = $form->get('recipientScope')->getData();
            if ($scope instanceof AdminRecipientScope && AdminRecipientScope::Selection === $scope) {
                $players = self::normalizeUserList($form->get('players')->getData());
                if ([] === $players) {
                    $form->get('players')->addError(new FormError('Sélectionnez au moins un joueur.'));
                }
            }
        });
    }

    /**
     * @return list<User>
     */
    private static function normalizeUserList(mixed $players): array
    {
        if ($players instanceof \Doctrine\Common\Collections\Collection) {
            return array_values($players->toArray());
        }

        if (\is_array($players)) {
            return array_values($players);
        }

        return [];
    }
}
