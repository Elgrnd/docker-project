<?php

namespace App\Service;

use App\Entity\BinaryFile;
use App\Entity\File;
use App\Entity\GroupeFileRepertoire;
use App\Entity\Repertoire;
use App\Entity\UtilisateurFileRepertoire;
use Doctrine\ORM\EntityManagerInterface;
use ZipArchive;

class RepertoireService
{
    public function __construct(
        private readonly string $storageDir,
    ) {}

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

        foreach ($repertoire->getAccesFilesUtilisateur() as $accesFile) {
            $fichierYaml = $accesFile->getFichierYaml();
            $archiveZip->addFromString(
                $cheminActuel . '/' . $fichierYaml->getNomFichier(),
                $fichierYaml->getContenuFichier()
            );
        }

        foreach ($repertoire->getAccesFilesUtilisateur() as $ufr) {
            $file = $ufr->getFile();
            $filepathInZip = $cheminActuel . '/' . $file->getNameFile();
            $archiveZip->addFromString($filepathInZip, $file->getBodyFile());
        }
    }

    public function deleteRepertoireWithFilesForUser(Repertoire $repertoire, EntityManagerInterface $em): void
    {
        $ufrRepo = $em->getRepository(UtilisateurFileRepertoire::class);
        $gfrRepo = $em->getRepository(GroupeFileRepertoire::class);

        foreach ($repertoire->getAccesFilesUtilisateur() as $ufr) {
            $file = $ufr->getFile();

            $em->remove($ufr);
            $em->flush();

            $this->deleteFileIfOrphan($file, $ufrRepo, $gfrRepo, $em);
        }

        foreach ($repertoire->getChildren() as $child) {
            $this->deleteRepertoireWithFilesForUser($child, $em);
        }

        $em->remove($repertoire);
    }

    public function deleteRepertoireWithFilesForGroup(Repertoire $repertoire, EntityManagerInterface $em): void
    {
        $ufrRepo = $em->getRepository(UtilisateurFileRepertoire::class);
        $gfrRepo = $em->getRepository(GroupeFileRepertoire::class);

        foreach ($repertoire->getAccesFilesGroupe() as $gfr) {
            $file = $gfr->getFile();

            $em->remove($gfr);
            $em->flush();

            $this->deleteFileIfOrphan($file, $ufrRepo, $gfrRepo, $em);
        }

        foreach ($repertoire->getChildren() as $child) {
            $this->deleteRepertoireWithFilesForGroup($child, $em);
        }

        $em->remove($repertoire);
    }

    private function deleteFileIfOrphan(
        File $file,
             $ufrRepo,
             $gfrRepo,
        EntityManagerInterface $em
    ): void {
        $stillHasUfr = $ufrRepo->findOneBy(['file' => $file]) !== null;
        $stillHasGfr = $gfrRepo->findOneBy(['file' => $file]) !== null;

        if ($stillHasUfr || $stillHasGfr) {
            return;
        }

        if ($file instanceof BinaryFile) {
            $storagePath = (string) $file->getStoragePath();
            $absolutePath = rtrim($this->storageDir, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . ltrim($storagePath, DIRECTORY_SEPARATOR);

            if (is_file($absolutePath)) {
                if (!unlink($absolutePath)) {
                    throw new \RuntimeException('Impossible de supprimer le fichier sur disque : ' . $absolutePath);
                }
            }
        }

        $em->remove($file);
        $em->flush();
    }
}