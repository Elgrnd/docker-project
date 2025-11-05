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

    #[ORM\ManyToOne(inversedBy: 'yamlFiles')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Groupe $groupe = null;

    /**
     * @var Collection<int, YamlFile>
     */
    #[ORM\ManyToMany(targetEntity: YamlFile::class)]
    private Collection $yamlFile;

    #[ORM\Column(length: 255)]
    private ?string $droit = null;

    public function __construct()
    {
        $this->yamlFile = new ArrayCollection();
    }

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

    /**
     * @return Collection<int, YamlFile>
     */
    public function getYamlFile(): Collection
    {
        return $this->yamlFile;
    }

    public function addYamlFile(YamlFile $yamlFile): static
    {
        if (!$this->yamlFile->contains($yamlFile)) {
            $this->yamlFile->add($yamlFile);
        }

        return $this;
    }

    public function removeYamlFile(YamlFile $yamlFile): static
    {
        $this->yamlFile->removeElement($yamlFile);

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
