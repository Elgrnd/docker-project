<?php

namespace App\Entity;

use App\Repository\YamlFileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: YamlFileRepository::class)]
#[UniqueEntity(fields: ['nameFile'], message: "Nom de fichier déjà utilisé")]
class YamlFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotNull]
    #[Assert\NotBlank]
    private ?string $nameFile = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $bodyFile = null;

    #[ORM\Column]
    private ?string $login = null;

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

    public function getBodyFile(): ?string
    {
        return $this->bodyFile;
    }

    public function setBodyFile(string $bodyFile): static
    {
        $this->bodyFile = $bodyFile;

        return $this;
    }

    public function getLogin(): ?int
    {
        return $this->login;
    }

    public function setLogin(string $login): static
    {
        $this->login = $login;

        return $this;
    }
}
