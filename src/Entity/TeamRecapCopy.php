<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TeamRecapCopyCategory;
use App\Repository\TeamRecapCopyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TeamRecapCopyRepository::class)]
#[ORM\Table(name: 'team_recap_copy')]
#[ORM\UniqueConstraint(name: 'UNIQ_TEAM_RECAP_COPY_CODE', fields: ['code'])]
#[UniqueEntity(fields: ['code'], message: 'Ce code existe déjà.')]
class TeamRecapCopy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9._-]+$/', message: 'Code : lettres minuscules, chiffres, points, tirets.')]
    private ?string $code = null;

    #[ORM\Column(length: 32, enumType: TeamRecapCopyCategory::class)]
    private TeamRecapCopyCategory $category = TeamRecapCopyCategory::IntroHigh;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $adminLabel = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $conditionHint = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank]
    private ?string $body = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

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

    public function getCategory(): TeamRecapCopyCategory
    {
        return $this->category;
    }

    public function setCategory(TeamRecapCopyCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getAdminLabel(): ?string
    {
        return $this->adminLabel;
    }

    public function setAdminLabel(string $adminLabel): static
    {
        $this->adminLabel = $adminLabel;

        return $this;
    }

    public function getConditionHint(): ?string
    {
        return $this->conditionHint;
    }

    public function setConditionHint(?string $conditionHint): static
    {
        $this->conditionHint = $conditionHint;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

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

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function __toString(): string
    {
        return (string) ($this->adminLabel ?? $this->code);
    }
}
