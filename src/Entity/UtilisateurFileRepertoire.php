<?php

namespace App\Entity;

use App\Repository\UtilisateurFileRepertoireRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UtilisateurFileRepertoireRepository::class)]
class UtilisateurFileRepertoire
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Utilisateur $utilisateur;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private File $file;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Repertoire::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Repertoire $repertoire;

    public function getUtilisateur(): Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(Utilisateur $utilisateur): void
    {
        $this->utilisateur = $utilisateur;
    }

    public function getFile(): File
    {
        return $this->file;
    }

    public function setFile(File $file): void
    {
        $this->file = $file;
    }

    public function getRepertoire(): Repertoire
    {
        return $this->repertoire;
    }

    public function setRepertoire(Repertoire $repertoire): void
    {
        $this->repertoire = $repertoire;
    }
}
