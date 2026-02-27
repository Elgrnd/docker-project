<?php

namespace App\Service;

use App\Entity\BinaryFile;
use App\Entity\File;
use App\Entity\GroupeFileRepertoire;
use App\Entity\Repertoire;
use App\Entity\UtilisateurFileRepertoire;
use Doctrine\ORM\EntityManagerInterface;
use ZipArchive;
use App\Entity\TextFile;
use App\Entity\Utilisateur;

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
            $fichier = $accesFile->getFile();
            $archiveZip->addFromString(
                $cheminActuel . '/' . $fichier->getNameFile(),
                $fichier->getBodyFile()
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

    public function importLocalDirIntoRepertoire(
        string $localRootDir,
        Repertoire $destRoot,
        Utilisateur $u,
        EntityManagerInterface $em
    ): array {
        $dirMap = ['' => $destRoot];
        $importedDirs = 0;
        $importedFiles = 0;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($localRootDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $full => $info) {
            $rel = ltrim(str_replace($localRootDir, '', $full), DIRECTORY_SEPARATOR);

            if ($rel === '.ssh' || str_starts_with($rel, '.ssh' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            if ($info->isDir()) {
                $parentRel = trim(dirname($rel), '.');
                $parentRel = $parentRel === '.' ? '' : $parentRel;

                $parent = $dirMap[$parentRel] ?? $destRoot;
                $name = $info->getBasename();

                $existing = $em->getRepository(Repertoire::class)->findOneBy([
                    'parent' => $parent,
                    'name' => $name,
                    'utilisateur_repertoire' => $u,
                ]);

                if (!$existing) {
                    $r = new Repertoire();
                    $r->setName($name);
                    $r->setParent($parent);
                    $r->setUtilisateurRepertoire($u);
                    $em->persist($r);
                    $em->flush();

                    $dirMap[$rel] = $r;
                    $importedDirs++;
                } else {
                    $dirMap[$rel] = $existing;
                }

                continue;
            }

            if (!$info->isFile()) continue;

            $parentRel = trim(dirname($rel), '.');
            $parentRel = $parentRel === '.' ? '' : $parentRel;
            $targetRep = $dirMap[$parentRel] ?? $destRoot;

            $nameFile = $info->getBasename();
            $ext = strtolower(ltrim((string) pathinfo($nameFile, PATHINFO_EXTENSION), '.'));

            if ($ext === '' || !in_array($ext, TextFile::allowedExtensions(), true)) {
                continue;
            }

            $content = @file_get_contents($full);
            if ($content === false) continue;

            $tf = new TextFile();
            $tf->setBodyFile($content);
            $tf->setNameFile($nameFile);
            $tf->setExtension($ext);
            $tf->setMimeType('text/plain');
            $tf->setUtilisateurFile($u);

            // perso
            $tf->setFromGitlab(false);
            $tf->setGitlabPath(null);
            $tf->setFromVm(false);
            $tf->setVmPath(null);

            $em->persist($tf);
            $em->flush();

            $link = new UtilisateurFileRepertoire();
            $link->setUtilisateur($u);
            $link->setFile($tf);
            $link->setRepertoire($targetRep);

            $em->persist($link);
            $em->flush();

            $importedFiles++;
        }

        return ['dirs' => $importedDirs, 'files' => $importedFiles];
    }

    public function clearRepertoire(Repertoire $repertoire, EntityManagerInterface $em): void
    {
        foreach ($repertoire->getAccesFilesUtilisateur() as $ufr) {
            $file = $ufr->getFile();

            $em->remove($ufr);
            $em->remove($file);
        }

        foreach ($repertoire->getChildrenActifs() as $child) {
            $this->clearRepertoire($child, $em);
            $em->remove($child);
        }

        $em->flush();
    }
}