<?php

namespace App\Entity;

use App\Repository\RepertoireRepository;
use DateTime;
use DateTimeInterface;
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

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist', 'remove'])]
    private Collection $children;


    #[ORM\ManyToOne(inversedBy: 'groupe_repertoire')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Groupe $groupe_repertoire = null;

    #[ORM\ManyToOne(inversedBy: 'utilisateur_repertoire')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Utilisateur $utilisateur_repertoire = null;

    #[ORM\OneToMany(targetEntity: UtilisateurYamlFileRepertoire::class, mappedBy: "repertoire", cascade: ['persist', 'remove'])]
    private Collection $accesYamlFilesUtilisateur;

    #[ORM\OneToMany(targetEntity: GroupeYamlFileRepertoire::class, mappedBy: "repertoire")]
    private Collection $accesYamlFilesGroupe;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $deletedAt = null;



    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->accesYamlFilesUtilisateur = new ArrayCollection();
        $this->accesYamlFilesGroupe = new ArrayCollection();

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

    // NOUVEAU : Méthode pour obtenir le chemin complet
    public function getFullPath(): string
    {
        if ($this->parent === null) {
            return $this->name;
        }
        return $this->parent->getFullPath() . ' / ' . $this->name;
    }

    public function getGroupeRepertoire(): ?Groupe
    {
        return $this->groupe_repertoire;
    }

    public function setGroupeRepertoire(?Groupe $groupe_repertoire): static
    {
        $this->groupe_repertoire = $groupe_repertoire;

        return $this;
    }

    public function getUtilisateurRepertoire(): ?Utilisateur
    {
        return $this->utilisateur_repertoire;
    }


    public function setUtilisateurRepertoire(?Utilisateur $utilisateur_repertoire): static
    {
        $this->utilisateur_repertoire = $utilisateur_repertoire;

        return $this;
    }

    public function getChildrenActifs(): array
    {
        return array_filter($this->children->toArray(), function (Repertoire $r) {
            return $r->getDeletedAt() === null;
        });
    }

    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeInterface $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    public function softDelete(): void
    {
        $this->deletedAt = new DateTime();
        foreach($this->accesYamlFilesUtilisateur as $utilisateurYamlFile) {
            $utilisateurYamlFile->getYamlFile()->setDeletedAt($this->deletedAt);
        }

        foreach ($this->children as $child) {
            $child->softDelete();
        }
    }

    public function canRestore(): bool
    {
        if ($this->parent !== null && $this->parent->isDeleted()) {
            return false;
        }
        return true;
    }

    public function restore(): void
    {
        $this->deletedAt = null;
        foreach($this->accesYamlFilesUtilisateur as $utilisateurYamlFile) {
            $utilisateurYamlFile->getYamlFile()->setDeletedAt($this->deletedAt);
        }

        foreach ($this->children as $child) {
            $child->restore();
        }
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function getAccesYamlFilesUtilisateur(): Collection
    {
        return $this->accesYamlFilesUtilisateur;
    }

    public function setAccesYamlFilesUtilisateur(Collection $accesYamlFilesUtilisateur): void
    {
        $this->accesYamlFilesUtilisateur = $accesYamlFilesUtilisateur;
    }
}