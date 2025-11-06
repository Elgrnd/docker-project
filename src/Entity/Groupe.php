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

    #[ORM\ManyToOne(inversedBy: 'groupes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateurChef = null;

    /**
     * @var Collection<int, Utilisateur>
     */
    #[ORM\ManyToMany(targetEntity: Utilisateur::class, inversedBy: 'groupesMembre')]
    private Collection $utilisateurs;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    /**
     * @var Collection<int, EtrePartageGroupe>
     */
    #[ORM\OneToMany(mappedBy: 'groupe', targetEntity: EtrePartageGroupe::class, cascade: ['remove'])]
    private Collection $etrePartageGroupes;


    public function __construct()
    {
        $this->utilisateurs = new ArrayCollection();
        $this->etrePartageGroupes = new ArrayCollection();
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

    /**
     * @return Collection<int, Utilisateur>
     */
    public function getUtilisateurs(): Collection
    {
        return $this->utilisateurs;
    }

    public function addUtilisateur(Utilisateur $utilisateur): static
    {
        if (!$this->utilisateurs->contains($utilisateur)) {
            $this->utilisateurs->add($utilisateur);
        }

        return $this;
    }

    public function removeUtilisateur(Utilisateur $utilisateur): static
    {
        $this->utilisateurs->removeElement($utilisateur);

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

    /**
     * @return Collection<int, EtrePartageGroupe>
     */
    public function getEtrePartageGroupes(): Collection
    {
        return $this->etrePartageGroupes;
    }

    public function addEtrePartageGroupe(EtrePartageGroupe $epg): static
    {
        if (!$this->etrePartageGroupes->contains($epg)) {
            $this->etrePartageGroupes->add($epg);
            $epg->setGroupe($this);
        }

        return $this;
    }

    public function removeEtrePartageGroupe(EtrePartageGroupe $epg): static
    {
        if ($this->etrePartageGroupes->removeElement($epg)) {
            if ($epg->getGroupe() === $this) {
                $epg->setGroupe(null);
            }
        }

        return $this;
    }
}
