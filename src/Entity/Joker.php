<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\JokerRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JokerRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_JOKER_CODE', fields: ['code'])]
class Joker
{
    public const CODE_DOUBLE_EQUIPE = 'double_equipe';

    public const CODE_PIQUE_POINTS = 'pique_points';

    public const CODE_ESPION = 'espion';

    public const CODE_DOUBLE_BUTEUR = 'double_buteur';

    public const CODE_INVERSE_BUTEUR = 'inverse_buteur';

    public const ESPION_PLACE_CONFIRMATION = 'Le joker Espion est définitif : une fois posé sur ce match, il ne peut plus être retiré. Souhaitez-vous le jouer ?';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(options: ['default' => 0])]
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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
            return $this;
        }

        $this->image = '/uploads/jokers/'.ltrim($filename, '/');

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
        return (string) $this->name;
    }
}
