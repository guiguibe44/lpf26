<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EditorialAuthorCountry;
use App\Repository\EditorialAuthorRepository;
use App\Service\UploadPathHelper;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EditorialAuthorRepository::class)]
class EditorialAuthor
{
    public const UPLOAD_SUBDIR = 'editorial-authors';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $firstName = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    private ?string $lastName = null;

    #[ORM\Column(length: 16)]
    private string $country = EditorialAuthorCountry::USA->value;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    /**
     * @var Collection<int, DashboardEditorial>
     */
    #[ORM\OneToMany(targetEntity: DashboardEditorial::class, mappedBy: 'author')]
    private Collection $editorials;

    public function __construct()
    {
        $this->editorials = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = trim($firstName);

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = trim($lastName);

        return $this;
    }

    public function getCountry(): EditorialAuthorCountry
    {
        return EditorialAuthorCountry::tryFrom($this->country) ?? EditorialAuthorCountry::USA;
    }

    public function setCountry(EditorialAuthorCountry|string $country): static
    {
        if (\is_string($country)) {
            $this->country = EditorialAuthorCountry::tryFrom($country)?->value ?? EditorialAuthorCountry::USA->value;
        } else {
            $this->country = $country->value;
        }

        return $this;
    }

    public function getCountryValue(): string
    {
        return $this->country;
    }

    public function setCountryValue(?string $country): static
    {
        $this->country = EditorialAuthorCountry::tryFrom((string) $country)?->value ?? EditorialAuthorCountry::USA->value;

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

    public function getAvatarPublicPath(): ?string
    {
        return UploadPathHelper::publicPath($this->avatar, self::UPLOAD_SUBDIR);
    }

    public function getDisplayName(): string
    {
        return trim(sprintf('%s %s', $this->firstName ?? '', $this->lastName ?? ''));
    }

    /**
     * @return Collection<int, DashboardEditorial>
     */
    public function getEditorials(): Collection
    {
        return $this->editorials;
    }

    public function __toString(): string
    {
        $name = $this->getDisplayName();

        if ('' !== $name) {
            return $name;
        }

        return sprintf('Auteur #%d', $this->id ?? 0);
    }
}
