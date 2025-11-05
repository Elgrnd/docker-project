<?php

namespace App\Entity;

use App\Repository\RepertoireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RepertoireRepository::class)]
class Repertoire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['persist', 'remove'])]
    private Collection $children;

    #[ORM\ManyToOne(inversedBy: 'repertoires')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur_id = null;

    // NOUVEAU : Relation avec YamlFile
    #[ORM\OneToMany(mappedBy: 'repertoire', targetEntity: YamlFile::class, cascade: ['persist'])]
    private Collection $yamlFiles;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->yamlFiles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getParent(): ?Repertoire
    {
        return $this->parent;
    }

    public function setParent(?Repertoire $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function setChildren(Collection $children): void
    {
        $this->children = $children;
    }

    public function getUtilisateurId(): ?Utilisateur
    {
        return $this->utilisateur_id;
    }

    public function setUtilisateurId(?Utilisateur $utilisateur_id): static
    {
        $this->utilisateur_id = $utilisateur_id;

        return $this;
    }

    // NOUVEAU : Getter/Setter pour YamlFiles
    /**
     * @return Collection<int, YamlFile>
     */
    public function getYamlFiles(): Collection
    {
        return $this->yamlFiles;
    }

    public function addYamlFile(YamlFile $yamlFile): static
    {
        if (!$this->yamlFiles->contains($yamlFile)) {
            $this->yamlFiles->add($yamlFile);
            $yamlFile->setRepertoire($this);
        }

        return $this;
    }

    public function removeYamlFile(YamlFile $yamlFile): static
    {
        if ($this->yamlFiles->removeElement($yamlFile)) {
            if ($yamlFile->getRepertoire() === $this) {
                $yamlFile->setRepertoire(null);
            }
        }

        return $this;
    }

    // NOUVEAU : Méthode pour vérifier si c'est le répertoire racine
    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    // NOUVEAU : Méthode pour obtenir le chemin complet
    public function getFullPath(): string
    {
        if ($this->parent === null) {
            return $this->name;
        }
        return $this->parent->getFullPath() . ' / ' . $this->name;
    }
}