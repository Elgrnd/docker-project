<?php

namespace App\Entity;

use App\Repository\GroupeYamlFileRepertoireRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupeYamlFileRepertoireRepository::class)]
class GroupeYamlFileRepertoire
{
    // Decommenter + enlever le null
//    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Repertoire::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Repertoire $repertoire = null;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: YamlFile::class)]
    #[ORM\JoinColumn(nullable: false)]
    private YamlFile $yamlFile;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Groupe::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Groupe $groupe;

    #[ORM\Column(length: 255)]
    private ?string $droit = null;

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

    public function getYamlFile(): YamlFile
    {
        return $this->yamlFile;
    }

    public function setYamlFile(YamlFile $yamlFile): void
    {
        $this->yamlFile = $yamlFile;
    }

    public function getGroupe(): Groupe
    {
        return $this->groupe;
    }

    public function setGroupe(Groupe $groupe): void
    {
        $this->groupe = $groupe;
    }


}
