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

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'yamlFiles')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Utilisateur $utilisateur = null;

    /**
     * @var Collection<int, EtrePartageGroupe>
     */
    #[ORM\ManyToMany(targetEntity: EtrePartageGroupe::class, mappedBy: 'yamlFile')]
    private Collection $etrePartageGroupes;

    #[ORM\ManyToOne(targetEntity: Repertoire::class, inversedBy: 'yamlFiles')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Repertoire $repertoire = null;
    public function __construct()
    {
        $this->etrePartageGroupes = new ArrayCollection();
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
    public function getRepertoire(): ?Repertoire
    {
        return $this->repertoire;
    }

    public function setRepertoire(?Repertoire $repertoire): static
    {
        $this->repertoire = $repertoire;
        return $this;
    }
}
