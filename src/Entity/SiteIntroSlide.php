<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SiteIntroVisualTheme;
use App\Repository\SiteIntroSlideRepository;
use App\Service\UploadPathHelper;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SiteIntroSlideRepository::class)]
class SiteIntroSlide
{
    public const UPLOAD_SUBDIR = 'site-intro';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $title = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $eyebrow = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /** Classe Tabler (ex. ti-users ou users). Affichée si pas d’image. */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(length: 32)]
    private string $visualTheme = SiteIntroVisualTheme::Welcome->value;

    /** Texte court sur la zone visuelle (ex. « × cote »). */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $visualBadge = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    public function getEyebrow(): ?string
    {
        return $this->eyebrow;
    }

    public function setEyebrow(?string $eyebrow): static
    {
        $this->eyebrow = null !== $eyebrow && '' !== trim($eyebrow) ? trim($eyebrow) : null;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = $body;

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

    public function getImagePublicPath(): ?string
    {
        return UploadPathHelper::publicPath($this->image, self::UPLOAD_SUBDIR);
    }

    public function hasUploadedImage(): bool
    {
        return null !== $this->getImagePublicPath();
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = null !== $icon && '' !== trim($icon) ? trim($icon) : null;

        return $this;
    }

    public function getIconClass(): ?string
    {
        if (null === $this->icon || '' === $this->icon) {
            return null;
        }

        $icon = $this->icon;
        if (str_starts_with($icon, 'ti ')) {
            return $icon;
        }
        if (str_starts_with($icon, 'ti-')) {
            return 'ti '.$icon;
        }

        return 'ti ti-'.$icon;
    }

    public function getVisualTheme(): SiteIntroVisualTheme
    {
        return SiteIntroVisualTheme::tryFrom($this->visualTheme) ?? SiteIntroVisualTheme::Neutral;
    }

    public function setVisualTheme(SiteIntroVisualTheme|string $visualTheme): static
    {
        if (\is_string($visualTheme)) {
            $this->visualTheme = SiteIntroVisualTheme::tryFrom($visualTheme)?->value
                ?? SiteIntroVisualTheme::Neutral->value;
        } else {
            $this->visualTheme = $visualTheme->value;
        }

        return $this;
    }

    /** Valeur chaîne (EasyAdmin, Twig, Doctrine). */
    public function getVisualThemeValue(): string
    {
        return $this->visualTheme;
    }

    public function setVisualThemeValue(?string $visualTheme): static
    {
        $this->visualTheme = SiteIntroVisualTheme::tryFrom((string) $visualTheme)?->value
            ?? SiteIntroVisualTheme::Neutral->value;

        return $this;
    }

    public function getVisualBadge(): ?string
    {
        return $this->visualBadge;
    }

    public function setVisualBadge(?string $visualBadge): static
    {
        $this->visualBadge = null !== $visualBadge && '' !== trim($visualBadge) ? trim($visualBadge) : null;

        return $this;
    }

    public function __toString(): string
    {
        return (string) ($this->title ?? 'Slide présentation');
    }
}
