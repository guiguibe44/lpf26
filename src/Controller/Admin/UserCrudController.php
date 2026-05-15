<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractAppCrudController
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
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

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('email'),
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
                ->setUploadDir('public/uploads/avatars')
                ->setRequired(false),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->applyPasswordAndRoles($entityInstance, true);
            $entityInstance->setAvatar($this->normalizeUploadPath($entityInstance->getAvatar(), '/uploads/avatars/'));
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->applyPasswordAndRoles($entityInstance, false);
            $entityInstance->setAvatar($this->normalizeUploadPath($entityInstance->getAvatar(), '/uploads/avatars/'));
        }

        parent::updateEntity($entityManager, $entityInstance);
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

    private function normalizeUploadPath(?string $path, string $prefix): ?string
    {
        if (null === $path || '' === $path || str_starts_with($path, '/uploads/')) {
            return $path;
        }

        return $prefix.ltrim($path, '/');
    }
}
