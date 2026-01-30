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
    #[ORM\OneToMany(targetEntity: UtilisateurGroupe::class, mappedBy: "groupe", cascade: ['persist'], orphanRemoval: true)]
    private Collection $utilisateur_groupe;

    #[ORM\Column(nullable: true)]
    private ?int $vmId = null;


    public function __construct()
    {
        $this->groupe_repertoire = new ArrayCollection();
        $this->utilisateur_groupe = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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
     * Retourne la *vraie* liste des membres (sans l'entité pivot)
     */
    public function getMembres(): array
    {
        return array_map(fn($ug) => $ug->getUtilisateur(), $this->utilisateur_groupe->toArray());
    }

    public function addUtilisateurGroupe(Utilisateur $u): UtilisateurGroupe
    {
        foreach ($this->utilisateur_groupe as $ug) {
            if ($ug->getUtilisateur() === $u) {
                return $ug;
            }
        }

        $ug = new UtilisateurGroupe();
        $ug->setUtilisateur($u);
        $ug->setGroupe($this);

        $this->utilisateur_groupe->add($ug);

        return $ug;
    }




    public function removeUtilisateurGroupe(Utilisateur $u): static
    {
        foreach ($this->utilisateur_groupe as $ug) {
            if ($ug->getUtilisateur() === $u) {
                $this->utilisateur_groupe->removeElement($ug);
                break;
            }
        }
        return $this;
    }

    public function contientMembre(Utilisateur $u): bool
    {
        foreach ($this->utilisateur_groupe as $ug) {
            if ($ug->getUtilisateur() === $u) {
                return true;
            }
        }
        return false;
    }

    public function getUtilisateurGroupePour(Utilisateur $user): ?UtilisateurGroupe
    {
        foreach ($this->utilisateur_groupe as $ug) {
            if ($ug->getUtilisateur() === $user) {
                return $ug;
            }
        }
        return null;
    }

    public function getVmId(): ?int
    {
        return $this->vmId;
    }

    public function setVmId(?int $vmId): static
    {
        $this->vmId = $vmId;

        return $this;
    }
}
