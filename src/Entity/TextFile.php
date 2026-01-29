<?php

namespace App\Entity;

use App\Repository\TextFileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

#[ORM\Entity(repositoryClass: TextFileRepository::class)]
#[ORM\Table(name: 'text_file')]
class TextFile extends File
{
    #[ORM\Column(type: Types::TEXT)]
    private ?string $bodyFile = null;

    /**
     * @var Collection<int, TextFileVersion>
     */
    #[ORM\OneToMany(targetEntity: TextFileVersion::class, mappedBy: 'textFileId', orphanRemoval: true)]
    private Collection $version;

    public function __construct()
    {
        parent::__construct();
        $this->version = new ArrayCollection();
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

    public static function allowedExtensions(): array
    {
        return [
            // manifests & config
            'yml','yaml','json','env','toml','ini','cfg','conf','properties','xml',

            // docker / tooling
            'dockerfile','dockerignore','gitignore',

            // docs
            'md','txt',

            // infra as code
            'tf','tfvars','hcl',

            // scripts (stockage uniquement)
            'sh','bash','zsh','ps1','py','js','ts',

            // build
            'makefile',
        ];
    }

    public static function allowedMimeTypes(): array
    {
        return [
            'text/plain',
            'text/markdown',
            'text/x-yaml',
            'application/x-yaml',
            'application/json',
            'text/xml',
            'application/xml',
            'text/x-shellscript',
            'text/x-ini',
            'text/x-properties',
            'application/toml',
            'application/octet-stream', // fallback (si text-only OK)
        ];
    }

    public function assertNotEmpty(string $content): void
    {
        if (trim($content) === '') {
            throw new DomainException("Le fichier ne peut pas être vide.");
        }
    }

    /**
     * @return Collection<int, TextFileVersion>
     */
    public function getVersion(): Collection
    {
        return $this->version;
    }

    public function addVersion(TextFileVersion $version): static
    {
        if (!$this->version->contains($version)) {
            $this->version->add($version);
            $version->setTextFileId($this);
        }
        return $this;
    }

    public function removeVersion(TextFileVersion $version): static
    {
        if ($this->version->removeElement($version)) {
            if ($version->getTextFileId() === $this) {
                $version->setTextFileId(null);
            }
        }
        return $this;
    }
}
