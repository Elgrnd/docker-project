<?php

namespace App\Entity;

use App\Repository\YamlFileGroupeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: YamlFileGroupeRepository::class)]
class YamlFileGroupe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nameFile = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bodyFile = null;

    #[ORM\ManyToOne(targetEntity: Groupe::class, inversedBy: 'yamlFiles')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Groupe $groupe = null;

    #[ORM\Column(length: 255)]
    private ?string $droit = null;

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

    public function setBodyFile(?string $bodyFile): static
    {
        $this->bodyFile = $bodyFile;

        return $this;
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
