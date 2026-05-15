<?php

namespace App\Entity;

use App\Repository\CountryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CountryRepository::class)]
class Country
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $drapeau = null;

    /** Lettre de poule CDM 2026 (A–L), nullable. */
    #[ORM\Column(length: 1, nullable: true)]
    private ?string $groupe = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDrapeau(): ?string
    {
        return $this->drapeau;
    }

    public function setDrapeau(?string $drapeau): static
    {
        $this->drapeau = $drapeau;

        return $this;
    }

    public function getGroupe(): ?string
    {
        return $this->groupe;
    }

    public function setGroupe(?string $groupe): static
    {
        if (null === $groupe || '' === $groupe) {
            $this->groupe = null;

            return $this;
        }

        $letter = mb_strtoupper($groupe);
        if (1 !== mb_strlen($letter) || $letter < 'A' || $letter > 'L') {
            throw new \InvalidArgumentException('Le groupe doit être une lettre entre A et L.');
        }

        $this->groupe = $letter;

        return $this;
    }

    public function getGroupPhaseLabel(): ?string
    {
        return null !== $this->groupe ? 'Group '.$this->groupe : null;
    }

    public function __toString(): string
    {
        return (string) $this->nom;
    }
}
