<?php

namespace App\Entity;

use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

#[ORM\Entity]
#[ORM\Table(name: 'file')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'dtype', type: 'string')]
#[ORM\DiscriminatorMap([
    'text' => TextFile::class,
    'binary' => BinaryFile::class,
])]
abstract class File
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(length: 255)]
    protected ?string $nameFile = null;

    #[ORM\Column(length: 100, nullable: true)]
    protected ?string $mimeType = null;

    #[ORM\Column(length: 15, nullable: true)]
    protected ?string $extension = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    protected ?string $description = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $fromGitlab = false;

    #[ORM\Column(length: 1024, nullable: true)]
    protected ?string $gitlabPath = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected ?DateTimeInterface $deletedAt = null;

    #[ORM\ManyToOne(inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: true)]
    protected ?Utilisateur $utilisateur_file = null;

    #[ORM\OneToMany(targetEntity: UtilisateurFileRepertoire::class, mappedBy: "file")]
    protected Collection $utilisateursParRepertoire;

    #[ORM\OneToMany(targetEntity: GroupeFileRepertoire::class, mappedBy: "file")]
    protected Collection $groupeParRepertoire;

    public function __construct()
    {
        $this->utilisateursParRepertoire = new ArrayCollection();
        $this->groupeParRepertoire = new ArrayCollection();
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

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType !== null ? strtolower($mimeType) : null;
        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(?string $extension): static
    {
        $this->extension = $extension !== null ? strtolower(ltrim($extension, '.')) : null;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function isFromGitlab(): bool
    {
        return $this->fromGitlab;
    }

    public function setFromGitlab(bool $fromGitlab): static
    {
        $this->fromGitlab = $fromGitlab;
        return $this;
    }

    public function getGitlabPath(): ?string
    {
        return $this->gitlabPath;
    }

    public function setGitlabPath(?string $gitlabPath): static
    {
        $this->gitlabPath = $gitlabPath;
        return $this;
    }

    public function getDeletedAt(): ?DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function getUtilisateurFile(): ?Utilisateur
    {
        return $this->utilisateur_file;
    }

    public function setUtilisateurFile(?Utilisateur $utilisateur_file): static
    {
        $this->utilisateur_file = $utilisateur_file;
        return $this;
    }

    public function getUtilisateursParRepertoire(): Collection
    {
        return $this->utilisateursParRepertoire;
    }

    public function setUtilisateursParRepertoire(Collection $utilisateursParRepertoire): void
    {
        $this->utilisateursParRepertoire = $utilisateursParRepertoire;
    }

    public function getGroupeParRepertoire(): Collection
    {
        return $this->groupeParRepertoire;
    }

    public function setGroupeParRepertoire(Collection $groupeParRepertoire): void
    {
        $this->groupeParRepertoire = $groupeParRepertoire;
    }

    public function isYaml(): bool
    {
        return in_array($this->getExtension(), ['yaml', 'yml'], true);
    }

    abstract public static function allowedExtensions(): array;
    abstract public static function allowedMimeTypes(): array;

    public function assertValidExtension(string $extension): void
    {
        $extension = strtolower(ltrim($extension, '.'));

        if ($extension === '') {
            throw new DomainException('Extension manquante ou non reconnue.');
        }

        if (!in_array($extension, static::allowedExtensions(), true)) {
            throw new DomainException('Extension de fichier non autorisée.');
        }
    }

    public function assertValidMimeType(?string $mimeType): void
    {
        if ($mimeType === null) {
            return;
        }

        $mimeType = strtolower($mimeType);

        $aliases = [
            'image/jpg' => 'image/jpeg',
            'application/yaml' => 'application/x-yaml',

            'application/x-httpd-php' => 'text/x-php',
            'application/php' => 'text/x-php',
            'application/x-php' => 'text/x-php',
        ];

        $mimeType = $aliases[$mimeType] ?? $mimeType;

        if (!in_array($mimeType, static::allowedMimeTypes(), true)) {
            throw new DomainException('Type MIME non autorisé.');
        }
    }

    public function isText(): bool
    {
        return $this instanceof TextFile;
    }

    public function isBinary(): bool
    {
        return $this instanceof BinaryFile;
    }
}
