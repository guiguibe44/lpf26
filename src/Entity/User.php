<?php

namespace App\Entity;

use App\Security\SuperAdminAuthorization;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse e-mail.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $cotisationPayee = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Buteur $buteurChoisi = null;

    /** Non mappé : saisie EasyAdmin / formulaires, jamais persisté tel quel. */
    private ?string $plainPassword = null;

    /** Non mappé : reflète ROLE_ADMIN en base pour les formulaires (voir {@see syncGrantAdminFromStoredRoles}). */
    private bool $grantAdmin = false;

    /** Non mappé : surnom du joueur (stocké sur {@see TeamMember}), éditable dans l’admin utilisateurs. */
    private ?string $nickname = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        if (SuperAdminAuthorization::isSuperAdminEmail((string) $this->email)) {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }

        return array_unique($roles);
    }

    public function isSuperAdmin(): bool
    {
        return SuperAdminAuthorization::isSuperAdminEmail((string) $this->email);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function isCotisationPayee(): bool
    {
        return $this->cotisationPayee;
    }

    public function setCotisationPayee(bool $cotisationPayee): static
    {
        $this->cotisationPayee = $cotisationPayee;

        return $this;
    }

    public function getButeurChoisi(): ?Buteur
    {
        return $this->buteurChoisi;
    }

    public function setButeurChoisi(?Buteur $buteurChoisi): static
    {
        $this->buteurChoisi = $buteurChoisi;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function isAdministrator(): bool
    {
        return \in_array('ROLE_ADMIN', $this->roles, true);
    }

    public function isGrantAdmin(): bool
    {
        return $this->grantAdmin;
    }

    public function setGrantAdmin(bool $grantAdmin): static
    {
        $this->grantAdmin = $grantAdmin;

        return $this;
    }

    public function getNickname(): ?string
    {
        return $this->nickname;
    }

    public function setNickname(?string $nickname): static
    {
        $this->nickname = null !== $nickname && '' !== trim($nickname) ? trim($nickname) : null;

        return $this;
    }

    #[ORM\PostLoad]
    public function syncGrantAdminFromStoredRoles(): void
    {
        $this->grantAdmin = \in_array('ROLE_ADMIN', $this->roles, true);
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $this->plainPassword = null;

        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function __toString(): string
    {
        return (string) $this->email;
    }
}
