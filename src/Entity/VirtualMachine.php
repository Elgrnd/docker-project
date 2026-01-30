<?php

namespace App\Entity;

use App\Repository\VirtualMachineRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VirtualMachineRepository::class)]
class VirtualMachine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $vmId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vmIp = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vmStatus = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $deleteVmAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVmId(): ?int
    {
        return $this->vmId;
    }

    public function setVmId(?int $vmId): static
    {
        $this->vmId = $vmId;

        return $this;
    }

    public function getVmIp(): ?string
    {
        return $this->vmIp;
    }

    public function setVmIp(?string $vmIp): static
    {
        $this->vmIp = $vmIp;

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

    public function getDeleteVmAt(): ?DateTimeImmutable
    {
        return $this->deleteVmAt;
    }

    public function setDeleteVmAt(?DateTimeImmutable $deleteVmAt): static
    {
        $this->deleteVmAt = $deleteVmAt;

        return $this;
    }
}
