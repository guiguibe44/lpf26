<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MainThemeBackgroundPosition;
use App\Enum\MainThemeBackgroundRepeat;
use App\Repository\MainThemeRepository;
use App\Service\UploadPathHelper;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MainThemeRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_MAIN_THEME_CODE', fields: ['code'])]
class MainTheme
{
    public const UPLOAD_SUBDIR = 'main-themes';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9_-]+$/', message: 'Code : minuscules, chiffres, tirets et underscores uniquement.')]
    private ?string $code = null;

    #[ORM\Column(length: 128)]
    #[Assert\NotBlank]
    private ?string $label = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDefault = false;

    /** Couleur de fond (#hex ou « transparent »). Ignorée si une image de fond est définie. */
    #[ORM\Column(length: 32, nullable: true)]
    #[Assert\Regex(pattern: '/^(#[0-9A-Fa-f]{3,8}|transparent)$/', message: 'Couleur invalide (ex. #f8fafc ou transparent).')]
    private ?string $backgroundColor = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $backgroundImage = null;

    #[ORM\Column(length: 32, options: ['default' => 'center center'])]
    private string $backgroundPosition = MainThemeBackgroundPosition::Center->value;

    #[ORM\Column(length: 32, options: ['default' => 'no-repeat'])]
    private string $backgroundRepeat = MainThemeBackgroundRepeat::NoRepeat->value;

    /** Voile coloré sur l'image de fond (ignoré sans image ou si opacité = 0). */
    #[ORM\Column(length: 32, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{3,8}$/', message: 'Couleur de voile invalide (ex. #000000).')]
    private ?string $backgroundOverlayColor = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\Range(min: 0, max: 100)]
    private int $backgroundOverlayOpacity = 0;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{3,8}$/')]
    private ?string $titleColor = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{3,8}$/')]
    private ?string $blockBackgroundColor = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{3,8}$/')]
    private ?string $blockTextColor = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{3,8}$/')]
    private ?string $buttonBackgroundColor = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{3,8}$/')]
    private ?string $buttonTextColor = null;

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
        $this->code = strtolower(trim($code));

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = trim($label);

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

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getBackgroundColor(): ?string
    {
        return $this->backgroundColor;
    }

    public function setBackgroundColor(?string $backgroundColor): static
    {
        $this->backgroundColor = null !== $backgroundColor && '' !== trim($backgroundColor)
            ? trim($backgroundColor)
            : null;

        return $this;
    }

    public function getBackgroundImage(): ?string
    {
        return $this->backgroundImage;
    }

    public function setBackgroundImage(?string $backgroundImage): static
    {
        $this->backgroundImage = $backgroundImage;

        return $this;
    }

    public function getBackgroundImagePublicPath(): ?string
    {
        return UploadPathHelper::publicPath($this->backgroundImage, self::UPLOAD_SUBDIR);
    }

    public function usesBackgroundImage(): bool
    {
        return null !== $this->backgroundImage && '' !== trim($this->backgroundImage);
    }

    public function getBackgroundPosition(): string
    {
        return $this->backgroundPosition;
    }

    public function setBackgroundPosition(string $backgroundPosition): static
    {
        $this->backgroundPosition = $backgroundPosition;

        return $this;
    }

    public function getBackgroundRepeat(): string
    {
        return $this->backgroundRepeat;
    }

    public function setBackgroundRepeat(string $backgroundRepeat): static
    {
        $this->backgroundRepeat = $backgroundRepeat;

        return $this;
    }

    public function getBackgroundOverlayColor(): ?string
    {
        return $this->backgroundOverlayColor;
    }

    public function setBackgroundOverlayColor(?string $backgroundOverlayColor): static
    {
        $this->backgroundOverlayColor = null !== $backgroundOverlayColor && '' !== trim($backgroundOverlayColor)
            ? trim($backgroundOverlayColor)
            : null;

        return $this;
    }

    public function getBackgroundOverlayOpacity(): int
    {
        return $this->backgroundOverlayOpacity;
    }

    public function setBackgroundOverlayOpacity(int $backgroundOverlayOpacity): static
    {
        $this->backgroundOverlayOpacity = max(0, min(100, $backgroundOverlayOpacity));

        return $this;
    }

    public function usesBackgroundOverlay(): bool
    {
        return $this->usesBackgroundImage()
            && $this->backgroundOverlayOpacity > 0
            && null !== $this->backgroundOverlayColor
            && '' !== $this->backgroundOverlayColor;
    }

    public function getTitleColor(): ?string
    {
        return $this->titleColor;
    }

    public function setTitleColor(string $titleColor): static
    {
        $this->titleColor = trim($titleColor);

        return $this;
    }

    public function getBlockBackgroundColor(): ?string
    {
        return $this->blockBackgroundColor;
    }

    public function setBlockBackgroundColor(string $blockBackgroundColor): static
    {
        $this->blockBackgroundColor = trim($blockBackgroundColor);

        return $this;
    }

    public function getBlockTextColor(): ?string
    {
        return $this->blockTextColor;
    }

    public function setBlockTextColor(string $blockTextColor): static
    {
        $this->blockTextColor = trim($blockTextColor);

        return $this;
    }

    public function getButtonBackgroundColor(): ?string
    {
        return $this->buttonBackgroundColor;
    }

    public function setButtonBackgroundColor(string $buttonBackgroundColor): static
    {
        $this->buttonBackgroundColor = trim($buttonBackgroundColor);

        return $this;
    }

    public function getButtonTextColor(): ?string
    {
        return $this->buttonTextColor;
    }

    public function setButtonTextColor(string $buttonTextColor): static
    {
        $this->buttonTextColor = trim($buttonTextColor);

        return $this;
    }

    public function __toString(): string
    {
        return (string) ($this->label ?? $this->code ?? 'Thème');
    }
}
