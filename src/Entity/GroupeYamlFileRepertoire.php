<?php

namespace App\Entity;

use App\Repository\UtilisateurYamlFileGroupeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UtilisateurYamlFileGroupeRepository::class)]
class GroupeYamlFileRepertoire
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Repertoire::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Repertoire $repertoire;

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
}
