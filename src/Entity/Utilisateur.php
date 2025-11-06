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

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: YamlFile::class, orphanRemoval: true)]
    private Collection $yamlFiles;

    #[ORM\Column(nullable: true)]
    private ?int $proxmoxVmid = null;

    /**
     * @var Collection<int, Groupe>
     */
    #[ORM\OneToMany(targetEntity: Groupe::class, mappedBy: 'utilisateurChef', orphanRemoval: true)]
    private Collection $groupes;

    /**
     * @var Collection<int, Groupe>
     */
    #[ORM\ManyToMany(targetEntity: Groupe::class, mappedBy: 'utilisateurs')]
    private Collection $groupesMembre;

    /**
     * @var Collection<int, Repertoire>
     */
    #[ORM\OneToMany(targetEntity: Repertoire::class, mappedBy: 'utilisateur_id', orphanRemoval: true)]
    private Collection $repertoires;

    public function __construct()
    {
        $this->yamlFiles = new ArrayCollection();
        $this->groupes = new ArrayCollection();
        $this->groupesMembre = new ArrayCollection();
        $this->repertoires = new ArrayCollection();
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

    public function getProxmoxVmid(): ?int
    {
        return $this->proxmoxVmid;
    }

    public function setProxmoxVmid(?int $proxmoxVmid): static
    {
        $this->proxmoxVmid = $proxmoxVmid;

        return $this;
    }
}
