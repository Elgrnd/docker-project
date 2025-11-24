<?php

namespace App\Entity;

use App\Repository\UtilisateurYamlFileRepertoireRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UtilisateurYamlFileRepertoireRepository::class)]
class UtilisateurYamlFileRepertoire
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Utilisateur $utilisateur;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: YamlFile::class)]
    #[ORM\JoinColumn(nullable: false)]
    private YamlFile $yamlFile;

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

    public function getYamlFile(): YamlFile
    {
        return $this->yamlFile;
    }

    public function setYamlFile(YamlFile $yamlFile): void
    {
        $this->yamlFile = $yamlFile;
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
