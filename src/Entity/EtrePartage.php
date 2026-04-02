<?php

namespace App\Entity;

use App\Repository\EtrePartageRepository;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

#[ORM\Entity(repositoryClass: EtrePartageRepository::class)]
class EtrePartage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?File $file = null;

    #[ORM\Column(length: 255)]
    private ?string $droit = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $datePartage = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): static
    {
        $this->file = $file;
        return $this;
    }

    public function getDroit(): ?string
    {
        return $this->droit;
    }

    public function setDroit(string $droit): static
    {
        $this->droit = $droit;
        return $this;
    }

    public function getDatePartage(): ?\DateTimeImmutable
    {
        return $this->datePartage;
    }

    public function setDatePartage(\DateTimeImmutable $datePartage): static
    {
        $this->datePartage = $datePartage;
        return $this;
    }

    public function assertNotSelfShare(Utilisateur $courant): void
    {
        if ($this->utilisateur === $courant) {
            throw new DomainException("Impossible de partager avec soi-même.");
        }
    }

    public function assertNotDuplicate(bool $exists): void
    {
        if ($exists) {
            throw new DomainException("Ce fichier est déjà partagé avec cet utilisateur.");
        }
    }
}
