<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
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

    #[ORM\Column(nullable: true)]
    private ?int $proxmoxVmid = null;

    /**
     * @var Collection<int, UtilisateurGroupe>
     */
    #[ORM\OneToMany(targetEntity: UtilisateurGroupe::class, mappedBy: "utilisateur", cascade: ['persist'], orphanRemoval: true)]
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

    #[ORM\OneToMany(targetEntity: UtilisateurYamlFileRepertoire::class, mappedBy: "utilisateur")]
    private Collection $yamlfilesParRepertoire;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $gitlabUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vmStatus = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $gitlabTokenCipher = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $gitlabTokenNonce = null;


    public function __construct()
    {
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
        return new ArrayCollection(
            array_map(fn($ug) => $ug->getGroupe(), $this->utilisateur_groupe->toArray())
        );
    }

    public function addUtilisateurGroupe(UtilisateurGroupe $ug): static
    {
        if (!$this->utilisateur_groupe->contains($ug)) {
            $this->utilisateur_groupe->add($ug);
        }
        return $this;
    }


    public function removeUtilisateurGroupe(Groupe $groupe): static
    {
        foreach ($this->utilisateur_groupe as $ug) {
            if ($ug->getGroupe() === $groupe) {
                $this->utilisateur_groupe->removeElement($ug);
                break;
            }
        }
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

    public function getGitlabUrl(): ?string
    {
        return $this->gitlabUrl;
    }

    public function setGitlabUrl(?string $gitlabUrl): self
    {
        $this->gitlabUrl = $gitlabUrl;
        return $this;
    }

    public function getUtilisateurGroupeRelation(Groupe $groupe): ?UtilisateurGroupe
    {
        foreach ($this->utilisateur_groupe as $ug) {
            if ($ug->getGroupe() === $groupe) {
                return $ug;
            }
        }
        return null;
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

    public function getVmStatus(): ?string
    {
        return $this->vmStatus;
    }

    public function setVmStatus(?string $vmStatus): static
    {
        $this->vmStatus = $vmStatus;

        return $this;
    }

    public function getGitlabTokenCipher(): ?string
    {
        return $this->gitlabTokenCipher;
    }

    public function setGitlabTokenCipher(?string $gitlabTokenCipher): self
    {
        $this->gitlabTokenCipher = $gitlabTokenCipher;
        return $this;
    }

    public function getGitlabTokenNonce(): ?string
    {
        return $this->gitlabTokenNonce;
    }

    public function setGitlabTokenNonce(?string $gitlabTokenNonce): self
    {
        $this->gitlabTokenNonce = $gitlabTokenNonce;
        return $this;
    }

}
