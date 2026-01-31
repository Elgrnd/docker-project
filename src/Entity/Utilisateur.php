<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

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

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: "Cette adresse email n'est pas valide !")]
    private ?string $adresseMail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $promotion = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $gitlabLastCommitSha = null;

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
    #[ORM\OneToMany(
        targetEntity: UtilisateurGroupe::class,
        mappedBy: "utilisateur",
        cascade: ['persist'],
        orphanRemoval: true
    )]
    private Collection $utilisateur_groupe;

    /**
     * ✅ Relation unique: tous les fichiers (TextFile + BinaryFile)
     * mappedBy doit correspondre à la propriété dans File.php : $utilisateur_file
     *
     * @var Collection<int, File>
     */
    #[ORM\OneToMany(targetEntity: File::class, mappedBy: 'utilisateur_file', orphanRemoval: true)]
    private Collection $files;

    /**
     * @var Collection<int, Repertoire>
     */
    #[ORM\OneToMany(targetEntity: Repertoire::class, mappedBy: 'utilisateur_repertoire', orphanRemoval: true)]
    private Collection $utilisateur_repertoire;

    #[ORM\OneToMany(targetEntity: UtilisateurFileRepertoire::class, mappedBy: "utilisateur")]
    private Collection $filesParRepertoire;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $gitlabUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vmStatus = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $gitlabTokenCipher = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $gitlabTokenNonce = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deleteVmAt = null;


    public function __construct()
    {
        $this->etrechef = new ArrayCollection();
        $this->utilisateur_groupe = new ArrayCollection();
        $this->files = new ArrayCollection();
        $this->utilisateur_repertoire = new ArrayCollection();
        $this->filesParRepertoire = new ArrayCollection();
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

    public function getUserIdentifier(): string
    {
        return (string) $this->login;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

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

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): void
    {
        $this->nom = $nom;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): void
    {
        $this->prenom = $prenom;
    }

    public function getPromotion(): ?string
    {
        return $this->promotion;
    }

    public function setPromotion(?string $promotion): void
    {
        $this->promotion = $promotion;
    }

    /**
     * ✅ UNIQUE GETTER
     * @return Collection<int, File>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(File $file): static
    {
        if (!$this->files->contains($file)) {
            $this->files->add($file);
            $file->setUtilisateurFile($this);
        }
        return $this;
    }

    public function removeFile(File $file): static
    {
        if ($this->files->removeElement($file)) {
            if ($file->getUtilisateurFile() === $this) {
                $file->setUtilisateurFile(null);
            }
        }
        return $this;
    }

    public function getUtilisateurRepertoire(): Collection
    {
        return $this->utilisateur_repertoire;
    }

    public function addUtilisateurRepertoire(Repertoire $r): static
    {
        if (!$this->utilisateur_repertoire->contains($r)) {
            $this->utilisateur_repertoire->add($r);
            $r->setUtilisateurRepertoire($this);
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

    public function getGitlabLastCommitSha(): ?string
    {
        return $this->gitlabLastCommitSha;
    }

    public function setGitlabLastCommitSha(?string $sha): self
    {
        $this->gitlabLastCommitSha = $sha;
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

    public function getVmStatus(): ?string
    {
        return $this->vmStatus;
    }

    public function setVmStatus(?string $vmStatus): static
    {
        $this->vmStatus = $vmStatus;
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

    /**
     * @return Collection<int, Groupe>
     */
    public function getUtilisateurGroupe(): Collection
    {
        return new ArrayCollection(
            array_map(fn (UtilisateurGroupe $ug) => $ug->getGroupe(), $this->utilisateur_groupe->toArray())
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

    public function getDeleteVmAt(): ?\DateTimeImmutable
    {
        return $this->deleteVmAt;
    }

    public function setDeleteVmAt(?\DateTimeImmutable $deleteVmAt): static
    {
        $this->deleteVmAt = $deleteVmAt;

        return $this;
    }

}
