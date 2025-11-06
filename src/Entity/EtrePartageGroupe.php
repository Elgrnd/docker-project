<?php

namespace App\Entity;

use App\Repository\EtrePartageGroupeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EtrePartageGroupeRepository::class)]
class EtrePartageGroupe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'etrePartageGroupes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Groupe $groupe = null;



    #[ORM\ManyToOne(targetEntity: YamlFile::class, inversedBy: 'etrePartageGroupes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?YamlFile $yamlFile = null;

    #[ORM\Column(length: 255)]
    private ?string $droit = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroupe(): ?Groupe
    {
        return $this->groupe;
    }

    public function setGroupe(?Groupe $groupe): static
    {
        $this->groupe = $groupe;

        return $this;
    }

    public function getYamlFile(): ?YamlFile
    {
        return $this->yamlFile;
    }

    public function setYamlFile(?YamlFile $yamlFile): static
    {
        $this->yamlFile = $yamlFile;
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
}
