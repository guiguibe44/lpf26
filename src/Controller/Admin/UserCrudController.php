<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\TeamMemberRepository;
use App\Service\UploadedImageFinalizeService;
use App\Service\UploadPathHelper;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractAppCrudController
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UploadedImageFinalizeService $uploadedImageFinalize,
        private readonly TeamMemberRepository $teamMemberRepository,
    ) {
    }
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    protected function getAdminSearchFields(): array
    {
        return [
            'id',
            'email',
            'avatar',
            'buteurChoisi.prenom',
            'buteurChoisi.nom',
        ];
    }

    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $this->hydrateNicknameFromTeamMember($entityDto->getInstance());

        return parent::createEditFormBuilder($entityDto, $formOptions, $context);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('email'),
            TextField::new('nickname', 'Surnom')
                ->setHelp('Surnom affiché dans le jeu et le forum (enregistré sur la fiche membre d’équipe).')
                ->formatValue(function (?string $value, ?User $user): string {
                    if (null !== $value && '' !== $value) {
                        return $value;
                    }
                    if (!$user instanceof User || null === $user->getId()) {
                        return '—';
                    }
                    $nickname = $this->teamMemberRepository->findOneBy(['player' => $user])?->getNickname();

                    return null !== $nickname && '' !== $nickname ? $nickname : '—';
                }),
            TextField::new('plainPassword', 'Mot de passe')
                ->setFormType(PasswordType::class)
                ->onlyOnForms()
                ->setRequired(Crud::PAGE_NEW === $pageName)
                ->hideOnIndex()
                ->setHelp(
                    Crud::PAGE_EDIT === $pageName
                        ? 'Laisser vide pour conserver le mot de passe actuel.'
                        : 'Minimum '.self::MIN_PASSWORD_LENGTH.' caractères.'
                ),
            BooleanField::new('grantAdmin', 'Administrateur')
                ->setHelp('Accès EasyAdmin en plus du jeu : équipe, pronostics et cotisation comme les autres joueurs.')
                ->hideOnIndex()
                ->onlyOnForms(),
            AssociationField::new('buteurChoisi')->setRequired(false),
            BooleanField::new('cotisationPayee')->setLabel('Cotisation payee'),
            ImageField::new('avatar')
                ->setLabel('Avatar')
                ->setBasePath('')
                ->formatValue(static fn (?string $value, ?User $user): ?string => UploadPathHelper::publicPath($user?->getAvatar(), 'avatars'))
                ->hideOnForm(),
            ImageField::new('avatarFilename')
                ->setLabel('Avatar')
                ->setBasePath('/uploads/avatars')
                ->setUploadDir('public/uploads/avatars')
                ->setRequired(false)
                ->onlyOnForms(),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->applyPasswordAndRoles($entityInstance, true);
            $this->applyOptimizedAvatar($entityInstance);
            $this->syncNicknameToTeamMember($entityManager, $entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->applyPasswordAndRoles($entityInstance, false);
            $this->applyOptimizedAvatar($entityInstance);
            $this->syncNicknameToTeamMember($entityManager, $entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hydrateNicknameFromTeamMember(object $entityInstance): void
    {
        if (!$entityInstance instanceof User || null === $entityInstance->getId()) {
            return;
        }

        $nickname = $this->teamMemberRepository->findOneBy(['player' => $entityInstance])?->getNickname();
        $entityInstance->setNickname($nickname);
    }

    private function syncNicknameToTeamMember(EntityManagerInterface $entityManager, User $user): void
    {
        $nickname = $user->getNickname();
        $member = $this->teamMemberRepository->findOneBy(['player' => $user]);
        if (null === $member) {
            return;
        }

        if (null === $nickname || '' === $nickname) {
            return;
        }

        $member->setNickname($nickname);
        $entityManager->persist($member);
    }

    private function applyPasswordAndRoles(User $user, bool $isNew): void
    {
        $plain = $user->getPlainPassword();
        if ($isNew) {
            if (null === $plain || '' === $plain) {
                throw new \RuntimeException('Le mot de passe est obligatoire pour un nouvel utilisateur.');
            }
            if (strlen($plain) < self::MIN_PASSWORD_LENGTH) {
                throw new \RuntimeException('Le mot de passe doit contenir au moins '.self::MIN_PASSWORD_LENGTH.' caractères.');
            }
            $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
        } elseif (null !== $plain && '' !== $plain) {
            if (strlen($plain) < self::MIN_PASSWORD_LENGTH) {
                throw new \RuntimeException('Le mot de passe doit contenir au moins '.self::MIN_PASSWORD_LENGTH.' caractères.');
            }
            $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
        }

        $user->setPlainPassword(null);
        $this->syncAdminRole($user);
    }

    private function syncAdminRole(User $user): void
    {
        $roles = array_values(array_filter(
            $user->getRoles(),
            static fn (string $role): bool => 'ROLE_USER' !== $role,
        ));

        if ($user->isGrantAdmin()) {
            if (!\in_array('ROLE_ADMIN', $roles, true)) {
                $roles[] = 'ROLE_ADMIN';
            }
        } else {
            $roles = array_values(array_filter(
                $roles,
                static fn (string $role): bool => 'ROLE_ADMIN' !== $role,
            ));
        }

        $user->setRoles($roles);
    }

    private function applyOptimizedAvatar(User $user): void
    {
        $avatar = $user->getAvatar();
        if (null === $avatar || '' === $avatar) {
            return;
        }

        $finalized = $this->uploadedImageFinalize->finalize(
            UploadPathHelper::normalizeStored($avatar, 'avatars') ?? basename($avatar),
            'avatars',
            asPublicPath: true,
        );

        if (null !== $finalized && '' !== $finalized) {
            $user->setAvatar($finalized);
        }
    }
}
