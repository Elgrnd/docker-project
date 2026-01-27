<?php

namespace App\Entity;

use App\Repository\GroupeFileRepertoireRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupeFileRepertoireRepository::class)]
class GroupeFileRepertoire
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Repertoire::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Repertoire $repertoire = null;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: false)]
    private File $file;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Groupe::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Groupe $groupe;

    #[ORM\Column(length: 255)]
    private ?string $droit = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    public function getDroit(): ?string
    {
        return $this->droit;
    }

    public function setDroit(string $droit): static
    {
        $this->droit = $droit;
        return $this;
    }

    public function getRepertoire(): Repertoire
    {
        return $this->repertoire;
    }

    public function setRepertoire(Repertoire $repertoire): void
    {
        $this->repertoire = $repertoire;
    }

    public function getFile(): File
    {
        return $this->file;
    }

    public function setFile(File $file): void
    {
        $this->file = $file;
    }

    public function getGroupe(): Groupe
    {
        return $this->groupe;
    }

    public function setGroupe(Groupe $groupe): void
    {
        $this->groupe = $groupe;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }
}
