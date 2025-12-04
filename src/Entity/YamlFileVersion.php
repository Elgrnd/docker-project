<?php

namespace App\Entity;

use App\Repository\YamlFileVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: YamlFileVersionRepository::class)]
class YamlFileVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $bodyFile = null;

    #[ORM\Column]
    private ?\DateTime $dateEdition = null;

    #[ORM\ManyToOne(inversedBy: 'commentaire')]
    #[ORM\JoinColumn(nullable: false)]
    private ?YamlFile $yamlFileId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDateEdition(): ?\DateTime
    {
        return $this->dateEdition;
    }

    public function setDateEdition(\DateTime $dateEdition): static
    {
        $this->dateEdition = $dateEdition;

        return $this;
    }

    public function getYamlFileId(): ?YamlFile
    {
        return $this->yamlFileId;
    }

    public function setYamlFileId(?YamlFile $yamlFileId): static
    {
        $this->yamlFileId = $yamlFileId;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }
}
