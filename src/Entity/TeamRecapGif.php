<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeamRecapGifRepository;
use App\TeamRecap\TeamRecapGifSlot;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TeamRecapGifRepository::class)]
#[ORM\Table(name: 'team_recap_gif')]
#[ORM\Index(name: 'IDX_TEAM_RECAP_GIF_SLOT', fields: ['slot'])]
class TeamRecapGif
{
    public const UPLOAD_SUBDIR = 'recap-email';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 96)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 96)]
    private ?string $slot = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $path = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlot(): ?string
    {
        return $this->slot;
    }

    public function setSlot(string $slot): static
    {
        $this->slot = $slot;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getPathFilename(): ?string
    {
        if (null === $this->path || '' === $this->path) {
            return null;
        }

        return basename($this->path);
    }

    public function setPathFilename(?string $filename): static
    {
        if (null === $filename || '' === $filename) {
            return $this;
        }

        $this->path = '/uploads/'.self::UPLOAD_SUBDIR.'/'.ltrim($filename, '/');

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

    public function getSlotAdminLabel(): string
    {
        return TeamRecapGifSlot::adminLabelFor((string) $this->slot);
    }

    public function __toString(): string
    {
        return sprintf('%s (#%d)', $this->getSlotAdminLabel(), (int) $this->id);
    }
}
