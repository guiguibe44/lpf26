<?php

namespace App\Entity;

use App\Repository\ButeurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ButeurRepository::class)]
class Buteur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Country $pays = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(nullable: true, unique: true)]
    private ?int $apiSportsPlayerId = null;

    /**
     * @var Collection<int, But>
     */
    #[ORM\OneToMany(mappedBy: 'buteur', targetEntity: But::class, orphanRemoval: true)]
    private Collection $buts;

    public function __construct()
    {
        $this->buts = new ArrayCollection();
    }

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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getPays(): ?Country
    {
        return $this->pays;
    }

    public function setPays(?Country $pays): static
    {
        $this->pays = $pays;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getApiSportsPlayerId(): ?int
    {
        return $this->apiSportsPlayerId;
    }

    public function setApiSportsPlayerId(?int $apiSportsPlayerId): static
    {
        $this->apiSportsPlayerId = $apiSportsPlayerId;

        return $this;
    }

    /**
     * @return Collection<int, But>
     */
    public function getButs(): Collection
    {
        return $this->buts;
    }

    public function addBut(But $but): static
    {
        if (!$this->buts->contains($but)) {
            $this->buts->add($but);
            $but->setButeur($this);
        }

        return $this;
    }

    public function removeBut(But $but): static
    {
        if ($this->buts->removeElement($but) && $but->getButeur() === $this) {
            $but->setButeur(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return trim(sprintf('%s %s', $this->prenom, $this->nom));
    }
}
