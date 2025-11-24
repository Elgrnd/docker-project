<?php

namespace App\Entity;

use App\Repository\GroupeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GroupeRepository::class)]
class Groupe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'etrechef')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $etreChef = null;

    /**
     * @var Collection<int, Repertoire>
     */
    #[ORM\OneToMany(targetEntity: Repertoire::class, mappedBy: 'groupe_repertoire', orphanRemoval: true)]
    private Collection $groupe_repertoire;

    /**
     * @var Collection<int, Utilisateur>
     */
    #[ORM\ManyToMany(targetEntity: Utilisateur::class, mappedBy: 'utilisateur_groupe')]
    private Collection $utilisateur_groupe;

    #[ORM\OneToMany(mappedBy: "utilisateur", targetEntity: GroupeYamlFileRepertoire::class)]
    private Collection $yamlfilesParRepertoire;


    public function __construct()
    {
        $this->utilisateurs = new ArrayCollection();
        $this->yamlFiles = new ArrayCollection();
        $this->groupe_repertoire = new ArrayCollection();
        $this->utilisateur_groupe = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateurChef(): ?Utilisateur
    {
        return $this->utilisateurChef;
    }

    public function setUtilisateurChef(?Utilisateur $utilisateurChef): static
    {
        $this->utilisateurChef = $utilisateurChef;

        return $this;
    }


    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }


    public function getEtreChef(): ?Utilisateur
    {
        return $this->etreChef;
    }

    public function setEtreChef(?Utilisateur $etreChef): static
    {
        $this->etreChef = $etreChef;

        return $this;
    }


    /**
     * @return Collection<int, Repertoire>
     */
    public function getGroupeRepertoire(): Collection
    {
        return $this->groupe_repertoire;
    }

    public function addGroupeRepertoire(Repertoire $groupeRepertoire): static
    {
        if (!$this->groupe_repertoire->contains($groupeRepertoire)) {
            $this->groupe_repertoire->add($groupeRepertoire);
            $groupeRepertoire->setGroupeRepertoire($this);
        }

        return $this;
    }

    public function removeGroupeRepertoire(Repertoire $groupeRepertoire): static
    {
        if ($this->groupe_repertoire->removeElement($groupeRepertoire)) {
            // set the owning side to null (unless already changed)
            if ($groupeRepertoire->getGroupeRepertoire() === $this) {
                $groupeRepertoire->setGroupeRepertoire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Utilisateur>
     */
    public function getUtilisateurGroupe(): Collection
    {
        return $this->utilisateur_groupe;
    }

    public function addUtilisateurGroupe(Utilisateur $utilisateurGroupe): static
    {
        if (!$this->utilisateur_groupe->contains($utilisateurGroupe)) {
            $this->utilisateur_groupe->add($utilisateurGroupe);
            $utilisateurGroupe->addUtilisateurGroupe($this);
        }

        return $this;
    }

    public function removeUtilisateurGroupe(Utilisateur $utilisateurGroupe): static
    {
        if ($this->utilisateur_groupe->removeElement($utilisateurGroupe)) {
            $utilisateurGroupe->removeUtilisateurGroupe($this);
        }

        return $this;
    }
}
