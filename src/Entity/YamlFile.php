<?php

namespace App\Entity;

use App\Repository\YamlFileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: YamlFileRepository::class)]
#[UniqueEntity(fields: ['nameFile'], message: "Nom de fichier déjà utilisé")]
class YamlFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotNull]
    #[Assert\NotBlank]
    private ?string $nameFile = ".";

    #[ORM\Column(type: Types::TEXT)]
    private ?string $bodyFile = null;

    #[ORM\ManyToOne(inversedBy: 'utilisateur_yamlfile')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur_yamlfile = null;

    #[ORM\OneToMany(mappedBy: "yamlFile", targetEntity: UtilisateurYamlfileRepertoire::class)]
    private Collection $utilisateursParRepertoire;

    #[ORM\OneToMany(mappedBy: "yamlFile", targetEntity: GroupeYamlFileRepertoire::class)]
    private Collection $groupeParRepertoire;

    public function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNameFile(): ?string
    {
        return $this->nameFile;
    }

    public function setNameFile(string $nameFile): static
    {
        $this->nameFile = $nameFile;

        return $this;
    }

    public function getBodyFile(): ?string
    {
        return $this->bodyFile;
    }

    public function setBodyFile(string $bodyFile): static
    {
        $this->bodyFile = $bodyFile;

        return $this;
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

    // NOUVEAU : Getter/Setter pour Repertoire

    public function setRepertoire(?Repertoire $repertoire): static
    {
        $this->repertoire = $repertoire;
        return $this;
    }


    public function getUtilisateurYamlfile(): ?Utilisateur
    {
        return $this->utilisateur_yamlfile;
    }

    public function setUtilisateurYamlfile(?Utilisateur $utilisateur_yamlfile): static
    {
        $this->utilisateur_yamlfile = $utilisateur_yamlfile;

        return $this;
    }

    public function getUtilisateursParRepertoire(): Collection
    {
        return $this->utilisateursParRepertoire;
    }

    public function setUtilisateursParRepertoire(Collection $utilisateursParRepertoire): void
    {
        $this->utilisateursParRepertoire = $utilisateursParRepertoire;
    }

    public function getGroupeParRepertoire(): Collection
    {
        return $this->groupeParRepertoire;
    }

    public function setGroupeParRepertoire(Collection $groupeParRepertoire): void
    {
        $this->groupeParRepertoire = $groupeParRepertoire;
    }


}
