<?php

namespace App\Controller;

use App\Entity\Groupe;
use App\Entity\GroupeFileRepertoire;
use App\Entity\TextFile;
use App\Entity\TextFileVersion;
use App\Repository\TextFileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class VersionController extends AbstractController
{
    #[Route('/versions/{textFileId}', name: 'listVersion')]
    #[Route('/groupe/{groupeId}/versions/{textFileId}', name: 'listVersion_groupe')]
    public function listVersion(
        int $textFileId,
        ?int $groupeId,
        EntityManagerInterface $em
    ): Response {

        $user = $this->getUser();

        $textFile = $em->getRepository(TextFile::class)->find($textFileId);
        if (!$textFile) {
            throw $this->createNotFoundException();
        }

        $groupe = null;

        if ($groupeId) {
            $groupe = $em->getRepository(Groupe::class)->find($groupeId);

            $gfr = $em->getRepository(GroupeFileRepertoire::class)
                ->findOneBy(['file' => $textFile, 'groupe' => $groupe]);

            if (!$gfr) {
                throw $this->createAccessDeniedException();
            }

            $this->denyAccessUnlessGranted('GROUPE_FILE_EDIT', $gfr);
        } else {
            if ($textFile->getUtilisateurFile() !== $user) {
                throw $this->createAccessDeniedException();
            }
        }

        $versions = $em->getRepository(TextFileVersion::class)
            ->findBy(['textFileId' => $textFile], ['dateEdition' => 'DESC']);

        return $this->render('version/listVersion.html.twig', [
            'textFile' => $textFile,
            'versions' => $versions,
            'groupe' => $groupe,
            'isGroupe' => $groupe !== null
        ]);
    }

    #[Route('/version/restore/{id}', name: 'version_restore')]
    #[Route('/groupe/{groupeId}/version/restore/{id}', name: 'version_restore_groupe')]
    public function restore(
        int $id,
        ?int $groupeId = null,
        EntityManagerInterface $em
    ): Response {

        $user = $this->getUser();

        $version = $em->getRepository(TextFileVersion::class)->find($id);

        if (!$version) {
            throw $this->createNotFoundException();
        }

        $textFile = $version->getTextFileId();
        $groupe = null;

        if ($groupeId) {
            $groupe = $em->getRepository(Groupe::class)->find($groupeId);

            $gfr = $em->getRepository(GroupeFileRepertoire::class)
                ->findOneBy([
                    'file' => $textFile,
                    'groupe' => $groupe
                ]);

            if (!$gfr) {
                throw $this->createAccessDeniedException();
            }

            $this->denyAccessUnlessGranted('GROUPE_FILE_EDIT', $gfr);

        } else {
            $this->denyAccessUnlessGranted('FILE_OWNER', $textFile);
        }

        // 🔹 Backup avant restauration
        $backup = new TextFileVersion();
        $backup->setBodyFile($textFile->getBodyFile());
        $backup->setDateEdition(new \DateTime());
        $backup->setUtilisateur($user);
        $backup->setTextFileId($textFile);
        $backup->setCommentaire(
            'Backup automatique par ' . $user->getLogin() .
            ' avant restauration (' . (new \DateTime())->format('d/m/Y H:i:s') . ')'
        );

        $em->persist($backup);

        // 🔹 Restauration
        $textFile->setBodyFile($version->getBodyFile());

        // 🔹 Trace restauration
        $restore = new TextFileVersion();
        $restore->setBodyFile($version->getBodyFile());
        $restore->setDateEdition(new \DateTime());
        $restore->setUtilisateur($user);
        $restore->setTextFileId($textFile);
        $restore->setCommentaire(
            'Restauration effectuée par ' . $user->getLogin() .
            ' (version du ' . $version->getDateEdition()->format('d/m/Y H:i:s') . ')'
        );

        $em->persist($restore);
        $em->flush();

        return $this->redirectToRoute(
            $groupe ? 'listVersion_groupe' : 'listVersion',
            $groupe
                ? ['groupeId' => $groupe->getId(), 'textFileId' => $textFile->getId()]
                : ['textFileId' => $textFile->getId()]
        );
    }

    #[Route('/version/detail/{id}', name: 'version_detail')]
    #[Route('/groupe/{groupeId}/version/detail/{id}', name: 'version_detail_groupe')]
    public function detail(
        int $id,
        ?int $groupeId = null,
        EntityManagerInterface $em
    ): Response {

        $version = $em->getRepository(TextFileVersion::class)->find($id);

        if (!$version) {
            throw $this->createNotFoundException('Version introuvable.');
        }

        $textFile = $version->getTextFileId();
        $groupe = null;

        if ($groupeId) {
            $groupe = $em->getRepository(Groupe::class)->find($groupeId);

            $gfr = $em->getRepository(GroupeFileRepertoire::class)
                ->findOneBy([
                    'file' => $textFile,
                    'groupe' => $groupe
                ]);

            if (!$gfr) {
                throw $this->createAccessDeniedException();
            }

            $this->denyAccessUnlessGranted('GROUPE_FILE_EDIT', $gfr);

        } else {
            $this->denyAccessUnlessGranted('FILE_OWNER', $textFile);
        }

        return $this->render('version/detail.html.twig', [
            'version' => $version,
            'textFile' => $textFile,
            'groupe' => $groupe,
            'isGroupe' => $groupe !== null
        ]);
    }
}
