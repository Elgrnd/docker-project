<?php

namespace App\Service;

use App\Entity\YamlFile;
use App\Repository\YamlFileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class YamlFileService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function stockerYaml(UploadedFile $file): YamlFile {

        // Lire le contenu du fichier yaml
        $content = file_get_contents($file->getPathname());

        // Créer l'entité
        $yaml = new YamlFile();
        $yaml->setNameFile($file->getClientOriginalName());
        $yaml->setBodyFile($content);
        $yaml->setIdUtilisateur("");

        // Faire persister les données
        $this->entityManager->persist($yaml);
        $this->entityManager->flush();

        return $yaml;

    }

}