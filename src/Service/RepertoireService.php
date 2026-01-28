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
     * @param Repertoire $repertoire   Le dossier qu’on est en train d’ajouter
     * @param ZipArchive $archiveZip   L’archive ZIP dans laquelle on écrit
     * @param string $cheminDansZip    Le chemin actuel dans le ZIP (ex : 'monDossier/sousDossier')
     */
    public function ajouterRepertoireDansZip(Repertoire $repertoire, ZipArchive $archiveZip, string $cheminDansZip): void
    {
        $cheminActuel = $cheminDansZip === ''
            ? $repertoire->getName()
            : $cheminDansZip . '/' . $repertoire->getName();

        if (!$archiveZip->locateName($cheminActuel . '/')) {
            $archiveZip->addEmptyDir($cheminActuel);
        }

        foreach ($repertoire->getChildrenActifs() as $enfant) {
            $this->ajouterRepertoireDansZip($enfant, $archiveZip, $cheminActuel);
        }

        foreach ($repertoire->getAccesYamlFilesUtilisateur() as $accesYaml) {
            $fichierYaml = $accesYaml->getFichierYaml();
            $archiveZip->addFromString(
                $cheminActuel . '/' . $fichierYaml->getNomFichier(),
                $fichierYaml->getContenuFichier()
            );
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