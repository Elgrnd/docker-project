<?php

namespace App\Service;

use App\Entity\Repertoire;
use App\Entity\UtilisateurYamlFileRepertoire;
use Doctrine\ORM\EntityManagerInterface;
use ZipArchive;

class RepertoireService
{
    /**
     * Ajoute un répertoire (et tout son contenu) dans un ZIP.
     *
     * @param Repertoire $repertoire  Le dossier qu’on est en train d'ajouter
     * @param ZipArchive $zip         L’archive ZIP dans laquelle on écrit
     * @param string $pathInZip       Le chemin actuel dans le ZIP (genre 'monDossier/sousDossier')
     */
    public function addRepertoireToZip(Repertoire $repertoire, ZipArchive $zip, string $pathInZip): void
    {
        $currentPath = ($pathInZip !== '' ? $pathInZip . '/' : '') . $repertoire->getName();

        $zip->addEmptyDir($currentPath);

        foreach ($repertoire->getAccesYamlFilesUtilisateur() as $uyr) {
            $yaml = $uyr->getYamlFile();
            $filepathInZip = $currentPath . '/' . $yaml->getNameFile();
            $zip->addFromString($filepathInZip, $yaml->getBodyFile());
        }

        foreach ($repertoire->getChildrenActifs() as $child) {
            $this->addRepertoireToZip($child, $zip, $currentPath);
        }
    }

    public function deleteRepertoireWithFiles(Repertoire $repertoire, EntityManagerInterface $em): void
    {
        foreach ($repertoire->getAccesYamlFilesUtilisateur() as $rel) {
            $em->remove($rel);
            $em->remove($rel->getYamlFile());
        }

        foreach ($repertoire->getAccesYamlFilesGroupe() as $rel) {
            $em->remove($rel);
            if ($em->getRepository(UtilisateurYamlFileRepertoire::class)->findOneBy(['yamlFile' => $rel->getYamlFile()]) === null) {
                $em->remove($rel->getYamlFile());
            }
        }

        foreach ($repertoire->getChildren() as $child) {
            $this->deleteRepertoireWithFiles($child, $em);
        }

        $em->remove($repertoire);
    }

}