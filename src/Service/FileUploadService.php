<?php

namespace App\Service;

use App\Entity\BinaryFile;
use App\Entity\TextFile;
use DomainException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class FileUploadService
{
    private const SAMPLE_BYTES = 8192;

    public function __construct(
        private readonly string $storageDir, // -> %kernel.project_dir%/var/storage
    ) {}

    /**
     * Crée et retourne un TextFile ou BinaryFile selon le contenu.
     * - Normalise l'extension (Dockerfile/.env/etc.)
     * - Valide whitelists extension + mimeType
     * - Pour TextFile: assertNotEmpty + parse YAML si isYaml() (throw ParseException si invalide)
     * - Pour BinaryFile: stockage sur disque dans storageDir
     *
     * @throws DomainException|ParseException
     */
    public function createFromUploadedFile(
        UploadedFile $uploadedFile,
        ?string $description = null,
        bool $validateYamlWhenYaml = true,
    ): TextFile|BinaryFile {
        $tmpPath = $uploadedFile->getRealPath();
        if ($tmpPath === false) {
            throw new DomainException('Fichier temporaire introuvable (upload interrompu).');
        }
        $size = (int) ($uploadedFile->getSize() ?? 0);
        $detectedMimeType = strtolower($uploadedFile->getMimeType() ?? 'application/octet-stream');

        $nameFile = $uploadedFile->getClientOriginalName();
        $detectedMimeType = strtolower($uploadedFile->getMimeType() ?? 'application/octet-stream');
        $clientExt = (string) $uploadedFile->getClientOriginalExtension();

        $extension = $this->normalizeExtension($nameFile, $clientExt);

        $sample = $this->readSample($uploadedFile);
        $isText = $this->classifyAsText($detectedMimeType, $sample, $extension);

        if ($isText) {
            $file = new TextFile();

            $file->setExtension($extension);
            $file->setMimeType($detectedMimeType);

            // Whitelists
            $file->assertValidExtension($extension);
            $file->assertValidMimeType($detectedMimeType);

            $content = file_get_contents($uploadedFile->getRealPath());
            if ($content === false) {
                throw new DomainException('Impossible de lire le contenu du fichier.');
            }

            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            }

            $file->assertNotEmpty($content);

            if ($validateYamlWhenYaml && $file->isYaml()) {
                Yaml::parse($content); // throw ParseException si invalide
            }

            $file->setNameFile($nameFile);
            $file->setBodyFile($content);
            $file->setDescription($description);

            return $file;
        }

        // Binary
        $file = new BinaryFile();

        $file->setExtension($extension);
        $file->setMimeType($detectedMimeType);

        $file->assertValidExtension($extension);
        $file->assertValidMimeType($detectedMimeType);

        $finalName = $this->storeBinary($uploadedFile, $extension);

        $file->setNameFile($nameFile);
        $file->setStoragePath($finalName);
        $file->setSize($size);
        $file->setDescription($description);

        return $file;
    }

    private function normalizeExtension(string $originalName, string $clientExt): string
    {
        $name = basename($originalName);

        if ($name === 'Dockerfile' || str_starts_with($name, 'Dockerfile.')) {
            return 'dockerfile';
        }
        if ($name === 'Makefile' || str_starts_with($name, 'Makefile.')) {
            return 'makefile';
        }
        if ($name === '.env' || str_starts_with($name, '.env.')) {
            return 'env';
        }
        if ($name === '.gitignore') {
            return 'gitignore';
        }
        if ($name === '.dockerignore') {
            return 'dockerignore';
        }

        return strtolower(ltrim($clientExt, '.'));
    }

    private function readSample(UploadedFile $uploadedFile): string
    {
        $fp = fopen($uploadedFile->getRealPath(), 'rb');
        if ($fp === false) {
            throw new DomainException('Impossible de lire le fichier uploadé.');
        }

        $sample = (string) fread($fp, self::SAMPLE_BYTES);
        fclose($fp);

        return $sample;
    }

    private function isProbablyText(string $sample): bool
    {
        if (str_contains($sample, "\0")) {
            return false;
        }
        return $sample === '' ? true : mb_check_encoding($sample, 'UTF-8');
    }

    private function classifyAsText(string $detectedMimeType, string $sample, string $extension): bool
    {
        if ($extension !== '' && in_array($extension, TextFile::allowedExtensions(), true)) {
            return $this->isProbablyText($sample);
        }

        if (str_starts_with($detectedMimeType, 'text/')) {
            return $this->isProbablyText($sample);
        }

        $textLike = [
            'application/json',
            'application/xml',
            'application/x-yaml',
            'application/yaml',
            'application/toml',
            'application/octet-stream',
        ];

        if (in_array($detectedMimeType, $textLike, true)) {
            return $this->isProbablyText($sample);
        }

        return false;
    }

    private function storeBinary(UploadedFile $uploadedFile, string $extension): string
    {
        if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0775, true) && !is_dir($this->storageDir)) {
            throw new DomainException('Impossible de créer le dossier de stockage des fichiers.');
        }

        $safeName = bin2hex(random_bytes(16));
        $finalName = $safeName . ($extension !== '' ? '.' . $extension : '');

        try {
            $uploadedFile->move($this->storageDir, $finalName);
        } catch (\Throwable) {
            throw new DomainException('Impossible de stocker le fichier binaire sur disque.');
        }

        return $finalName;
    }
}
