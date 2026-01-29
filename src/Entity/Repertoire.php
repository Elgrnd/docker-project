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

    #[ORM\OneToMany(targetEntity: UtilisateurFileRepertoire::class, mappedBy: "repertoire", cascade: ['persist', 'remove'])]
    private Collection $accesFilesUtilisateur;

    #[ORM\OneToMany(targetEntity: GroupeFileRepertoire::class, mappedBy: "repertoire")]
    private Collection $accesFilesGroupe;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $deletedAt = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->accesFilesUtilisateur = new ArrayCollection();
        $this->accesFilesGroupe = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getParent(): ?Repertoire { return $this->parent; }

    public function setParent(?Repertoire $parent): void { $this->parent = $parent; }

    public function getChildren(): Collection { return $this->children; }

    public function setChildren(Collection $children): void { $this->children = $children; }

    public function getFullPath(): string
    {
        if ($this->parent === null) return $this->name;
        return $this->parent->getFullPath() . ' / ' . $this->name;
    }

    public function getGroupeRepertoire(): ?Groupe { return $this->groupe_repertoire; }

    public function setGroupeRepertoire(?Groupe $groupe_repertoire): static
    {
        $this->groupe_repertoire = $groupe_repertoire;
        return $this;
    }

    public function getUtilisateurRepertoire(): ?Utilisateur { return $this->utilisateur_repertoire; }

    public function setUtilisateurRepertoire(?Utilisateur $utilisateur_repertoire): static
    {
        $this->utilisateur_repertoire = $utilisateur_repertoire;
        return $this;
    }

    public function getDeletedAt(): ?DateTimeInterface { return $this->deletedAt; }

    public function setDeletedAt(?DateTimeInterface $deletedAt): void { $this->deletedAt = $deletedAt; }

    public function isDeleted(): bool { return $this->deletedAt !== null; }

    public function getAccesFilesUtilisateur(): Collection { return $this->accesFilesUtilisateur; }

    public function setAccesFilesUtilisateur(Collection $accesFilesUtilisateur): void
    {
        $this->accesFilesUtilisateur = $accesFilesUtilisateur;
    }

    public function getAccesFilesGroupe(): Collection { return $this->accesFilesGroupe; }

    public function setAccesFilesGroupe(Collection $accesFilesGroupe): void
    {
        $this->accesFilesGroupe = $accesFilesGroupe;
    }

    public function softDelete(): void
    {
        $this->deletedAt = new DateTime();

        foreach ($this->accesFilesUtilisateur as $rel) {
            $rel->getFile()->setDeletedAt($this->deletedAt);
        }

        foreach ($this->children as $child) {
            $child->softDelete();
        }
    }

    public function getChildrenActifs(): array
    {
        return array_filter(
            $this->children->toArray(),
            fn (Repertoire $r) => $r->getDeletedAt() === null
        );
    }


    public function softDeleteForGroupe(Groupe $groupe): void
    {
        $this->deletedAt = new DateTime();

        foreach ($this->accesFilesGroupe as $gyr) {
            if ($gyr->getGroupe() === $groupe) {
                $gyr->setDeletedAt($this->deletedAt);
            }
        }

        foreach ($this->children as $child) {
            $child->softDeleteForGroupe($groupe);
        }
    }

    public function restore(): void
    {
        $this->deletedAt = null;

        foreach ($this->accesFilesUtilisateur as $rel) {
            $rel->getFile()->setDeletedAt(null);
        }
    }

    public function isRoot()
    {
        return $this->parent === null;
    }

    public function canRestore(): bool
    {
        if ($this->parent !== null && $this->parent->isDeleted()) {
            return false;
        }
        return true;
    }

    public function getDeletedFilesGroupe(Groupe $groupe): array
    {
        $files = [];

        foreach ($this->accesFilesGroupe as $rel) {
            if (
                $rel->getGroupe() === $groupe &&
                $rel->getDeletedAt() !== null
            ) {
                $files[] = $rel->getFile();
            }
        }

        foreach ($this->children as $child) {
            $files = array_merge(
                $files,
                $child->getDeletedFilesGroupe($groupe)
            );
        }

        return $files;
    }
}
