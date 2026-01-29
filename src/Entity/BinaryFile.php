<?php

namespace App\Entity;

use App\Repository\BinaryFileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BinaryFileRepository::class)]
#[ORM\Table(name: 'binary_file')]
class BinaryFile extends File
{
    #[ORM\Column(length: 255)]
    private ?string $storagePath = null;

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    public function getStoragePath(): ?string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): static
    {
        $this->storagePath = $storagePath;
        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): static
    {
        $this->size = $size;
        return $this;
    }

    public static function allowedExtensions(): array
    {
        return [
            // Images
            'png','jpg','jpeg','gif','svg',

            // Certificats / clés
            'pem','crt','cer','key','p12',

            // Docs binaires
            'pdf',
        ];
    }

    public static function allowedMimeTypes(): array
    {
        return [
            'application/octet-stream',
            'image/png',
            'image/jpeg',
            'image/svg+xml',
            'application/pdf',
            'application/x-pem-file',
            'application/x-x509-ca-cert',
        ];
    }
}
