<?php

namespace App\Service;

use App\Entity\Utilisateur;
use App\Entity\YamlFile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class YamlFileManager implements YamlFileManagerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function stockerYaml(UploadedFile $file, Utilisateur $user): YamlFile {

        // Lire le contenu du fichier yaml
        $content = file_get_contents($file->getPathname());

        // Créer l'entité
        $yaml = new YamlFile();
        $yaml->setNameFile($file->getClientOriginalName());
        $yaml->setBodyFile($content);
        $yaml->setLogin($user->getLogin());

        // Faire persister les données
        $this->entityManager->persist($yaml);
        $this->entityManager->flush();

        return $yaml;

    }

}