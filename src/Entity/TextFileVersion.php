<?php

namespace App\Entity;

use App\Repository\TextFileVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TextFileVersionRepository::class)]
class TextFileVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $bodyFile = null;

    #[ORM\Column]
    private ?\DateTime $dateEdition = null;

    #[ORM\ManyToOne(inversedBy: 'version')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TextFile $textFileId = null;

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

    public function getTextFileId(): ?TextFile
    {
        return $this->textFileId;
    }

    public function setTextFileId(?TextFile $textFileId): static
    {
        $this->textFileId = $textFileId;
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
