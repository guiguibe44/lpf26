<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\BadgeCategory;
use App\Enum\BadgeOutcome;
use App\Enum\BadgeScope;
use App\Repository\BadgeDefinitionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BadgeDefinitionRepository::class)]
#[ORM\Table(name: 'badge_definition')]
#[ORM\UniqueConstraint(name: 'UNIQ_BADGE_DEFINITION_CODE', fields: ['code'])]
#[UniqueEntity(fields: ['code'], message: 'Ce code badge existe déjà.')]
class BadgeDefinition
{
    public const UPLOAD_SUBDIR = 'badges';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9._-]+$/', message: 'Code : lettres minuscules, chiffres, points, tirets.')]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 32, enumType: BadgeCategory::class)]
    private BadgeCategory $category = BadgeCategory::Pronostic;

    #[ORM\Column(length: 16, enumType: BadgeScope::class)]
    private BadgeScope $scope = BadgeScope::Player;

    #[ORM\Column(length: 16, enumType: BadgeOutcome::class, nullable: true)]
    private ?BadgeOutcome $outcome = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $criterionHint = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $flavorText = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $ironic = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column]
    private int $sortOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCategory(): BadgeCategory
    {
        return $this->category;
    }

    public function setCategory(BadgeCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getScope(): BadgeScope
    {
        return $this->scope;
    }

    public function setScope(BadgeScope $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function getOutcome(): ?BadgeOutcome
    {
        return $this->outcome;
    }

    public function setOutcome(?BadgeOutcome $outcome): static
    {
        $this->outcome = $outcome;

        return $this;
    }

    public function getCriterionHint(): ?string
    {
        return $this->criterionHint;
    }

    public function setCriterionHint(?string $criterionHint): static
    {
        $this->criterionHint = $criterionHint;

        return $this;
    }

    public function getFlavorText(): ?string
    {
        return $this->flavorText;
    }

    public function setFlavorText(?string $flavorText): static
    {
        $this->flavorText = $flavorText;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getImageFilename(): ?string
    {
        if (null === $this->image || '' === $this->image) {
            return null;
        }

        return basename($this->image);
    }

    public function setImageFilename(?string $filename): static
    {
        if (null === $filename || '' === $filename) {
            $this->image = null;

            return $this;
        }

        $this->image = '/uploads/'.self::UPLOAD_SUBDIR.'/'.ltrim($filename, '/');

        return $this;
    }

    public function isIronic(): bool
    {
        return $this->ironic;
    }

    public function setIronic(bool $ironic): static
    {
        $this->ironic = $ironic;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function __toString(): string
    {
        return (string) ($this->name ?? $this->code ?? 'Badge');
    }
}
