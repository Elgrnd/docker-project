<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: ['adresseMail'], message: "Adresse mail déjà prise")]
#[UniqueEntity(fields: ['login'], message: "Login déjà pris")]
#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_LOGIN', fields: ['login'])]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotNull]
    #[Assert\NotBlank]
    #[Assert\Length(min: 4, minMessage: 'Il faut au moins 4 caractères!')]
    #[Assert\Length(max: 200, maxMessage: 'Il faut moins de 200 caractères!')]
    private ?string $login = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Email(message: "Cette adresse email n'est pas valide !")]
    private ?string $adresseMail = null;

    /**
     * @var Collection<int, Groupe>
     */
    #[ORM\OneToMany(targetEntity: Groupe::class, mappedBy: 'etreChef', orphanRemoval: true)]
    private Collection $etrechef;

    /**
     * @var Collection<int, Groupe>
     */
    #[ORM\ManyToMany(targetEntity: Groupe::class, inversedBy: 'utilisateur_groupe')]
    #[ORM\JoinTable(name: "utilisateur_groupe")]
    private Collection $utilisateur_groupe;

    /**
     * @var Collection<int, YamlFile>
     */
    #[ORM\OneToMany(targetEntity: YamlFile::class, mappedBy: 'utilisateur_yamlfile', orphanRemoval: true)]
    private Collection $utilisateur_yamlfile;

    /**
     * @var Collection<int, Repertoire>
     */
    #[ORM\OneToMany(targetEntity: Repertoire::class, mappedBy: 'utilisateur_repertoire', orphanRemoval: true)]
    private Collection $utilisateur_repertoire;

    #[ORM\OneToMany(mappedBy: "utilisateur", targetEntity: UtilisateurYamlfileRepertoire::class)]
    private Collection $yamlfilesParRepertoire;

    public function __construct()
    {
        $this->yamlFiles = new ArrayCollection();
        $this->groupes = new ArrayCollection();
        $this->groupesMembre = new ArrayCollection();
        $this->repertoires = new ArrayCollection();
        $this->etrechef = new ArrayCollection();
        $this->utilisateur_groupe = new ArrayCollection();
        $this->utilisateur_yamlfile = new ArrayCollection();
        $this->utilisateur_repertoire = new ArrayCollection();

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function setLogin(string $login): static
    {
        $this->login = $login;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->login;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getAdresseMail(): ?string
    {
        return $this->adresseMail;
    }

    public function setAdresseMail(string $adresseMail): static
    {
        $this->adresseMail = $adresseMail;

        return $this;
    }

    public function getYamlFiles(): Collection
    {
        return $this->yamlFiles;
    }

    public function addYamlFile(YamlFile $yamlFile): static
    {
        if (!$this->yamlFiles->contains($yamlFile)) {
            $this->yamlFiles->add($yamlFile);
            $yamlFile->setUtilisateur($this);
        }

        return $this;
    }

    public function removeYamlFile(YamlFile $yamlFile): static
    {
        if ($this->yamlFiles->removeElement($yamlFile)) {
            if ($yamlFile->getUtilisateur() === $this) {
                $yamlFile->setUtilisateur(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Groupe>
     */
    public function getGroupes(): Collection
    {
        return $this->groupes;
    }

    public function addGroupe(Groupe $groupe): static
    {
        if (!$this->groupes->contains($groupe)) {
            $this->groupes->add($groupe);
            $groupe->setUtilisateurChef($this);
        }

        return $this;
    }

    public function removeGroupe(Groupe $groupe): static
    {
        if ($this->groupes->removeElement($groupe)) {
            // set the owning side to null (unless already changed)
            if ($groupe->getUtilisateurChef() === $this) {
                $groupe->setUtilisateurChef(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Groupe>
     */
    public function getGroupesMembre(): Collection
    {
        return $this->groupesMembre;
    }

    public function addGroupesMembre(Groupe $groupesMembre): static
    {
        if (!$this->groupesMembre->contains($groupesMembre)) {
            $this->groupesMembre->add($groupesMembre);
            $groupesMembre->addUtilisateur($this);
        }

        return $this;
    }

    public function removeGroupesMembre(Groupe $groupesMembre): static
    {
        if ($this->groupesMembre->removeElement($groupesMembre)) {
            $groupesMembre->removeUtilisateur($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Repertoire>
     */
    public function getRepertoires(): Collection
    {
        return $this->repertoires;
    }

    public function addRepertoire(Repertoire $repertoire): static
    {
        if (!$this->repertoires->contains($repertoire)) {
            $this->repertoires->add($repertoire);
            $repertoire->setUtilisateurId($this);
        }

        return $this;
    }

    public function removeRepertoire(Repertoire $repertoire): static
    {
        if ($this->repertoires->removeElement($repertoire)) {
            // set the owning side to null (unless already changed)
            if ($repertoire->getUtilisateurId() === $this) {
                $repertoire->setUtilisateurId(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Groupe>
     */
    public function getEtrechef(): Collection
    {
        return $this->etrechef;
    }

    public function addEtrechef(Groupe $etrechef): static
    {
        if (!$this->etrechef->contains($etrechef)) {
            $this->etrechef->add($etrechef);
            $etrechef->setEtreChef($this);
        }

        return $this;
    }

    public function removeEtrechef(Groupe $etrechef): static
    {
        if ($this->etrechef->removeElement($etrechef)) {
            // set the owning side to null (unless already changed)
            if ($etrechef->getEtreChef() === $this) {
                $etrechef->setEtreChef(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Groupe>
     */
    public function getUtilisateurGroupe(): Collection
    {
        return $this->utilisateur_groupe;
    }

    public function addUtilisateurGroupe(Groupe $utilisateurGroupe): static
    {
        if (!$this->utilisateur_groupe->contains($utilisateurGroupe)) {
            $this->utilisateur_groupe->add($utilisateurGroupe);
        }

        return $this;
    }

    public function removeUtilisateurGroupe(Groupe $utilisateurGroupe): static
    {
        $this->utilisateur_groupe->removeElement($utilisateurGroupe);

        return $this;
    }

    /**
     * @return Collection<int, YamlFile>
     */
    public function getUtilisateurYamlfile(): Collection
    {
        return $this->utilisateur_yamlfile;
    }

    public function addUtilisateurYamlfile(YamlFile $utilisateurYamlfile): static
    {
        if (!$this->utilisateur_yamlfile->contains($utilisateurYamlfile)) {
            $this->utilisateur_yamlfile->add($utilisateurYamlfile);
            $utilisateurYamlfile->setUtilisateurYamlfile($this);
        }

        return $this;
    }

    public function removeUtilisateurYamlfile(YamlFile $utilisateurYamlfile): static
    {
        if ($this->utilisateur_yamlfile->removeElement($utilisateurYamlfile)) {
            // set the owning side to null (unless already changed)
            if ($utilisateurYamlfile->getUtilisateurYamlfile() === $this) {
                $utilisateurYamlfile->setUtilisateurYamlfile(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Repertoire>
     */
    public function getUtilisateurRepertoire(): Collection
    {
        return $this->utilisateur_repertoire;
    }

    public function addUtilisateurRepertoire(Repertoire $utilisateurRepertoire): static
    {
        if (!$this->utilisateur_repertoire->contains($utilisateurRepertoire)) {
            $this->utilisateur_repertoire->add($utilisateurRepertoire);
            $utilisateurRepertoire->setUtilisateurRepertoire($this);
        }

        return $this;
    }

    public function removeUtilisateurRepertoire(Repertoire $utilisateurRepertoire): static
    {
        if ($this->utilisateur_repertoire->removeElement($utilisateurRepertoire)) {
            // set the owning side to null (unless already changed)
            if ($utilisateurRepertoire->getUtilisateurRepertoire() === $this) {
                $utilisateurRepertoire->setUtilisateurRepertoire(null);
            }
        }

        return $this;
    }
}
