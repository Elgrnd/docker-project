<?php

namespace App\Entity;

use App\Repository\EtrePartageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EtrePartageRepository::class)]
class EtrePartage
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
    private ?int $idFile = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $datePartage = null;

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

    public function getIdFile(): ?int
    {
        return $this->idFile;
    }

    public function setIdFile(int $idFile): static
    {
        $this->idFile = $idFile;

        return $this;
    }

    public function getDatePartage(): ?\DateTimeImmutable
    {
        return $this->datePartage;
    }

    public function setDatePartage(\DateTimeImmutable $datePartage): static
    {
        $this->datePartage = $datePartage;

        return $this;
    }
}
