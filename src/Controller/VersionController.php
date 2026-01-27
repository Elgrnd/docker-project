<?php

namespace App\Controller;

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
    #[IsGranted('ROLE_USER')]
    #[Route('/listVersion/{id}', name: 'listVersion')]
    public function listVersion(int $id, EntityManagerInterface $entityManager): Response
    {
        $utilisateur = $this->getUser();

        $textFile = $entityManager->getRepository(TextFile::class)->find($id);

        if (!$textFile) {
            $this->addFlash('error', 'Fichier introuvable.');
            return $this->redirectToRoute('repertoire');
        }

        if ($textFile->getUtilisateurFile() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à voir les versions de ce fichier.');
        }

        $versions = $entityManager->getRepository(TextFileVersion::class)
            ->findBy(['textFileId' => $textFile], ['dateEdition' => 'DESC']);

        usort($versions, function(TextFileVersion $a, TextFileVersion $b) {
            return $b->getDateEdition() <=> $a->getDateEdition();
        });

        return $this->render('version/listVersion.html.twig', [
            'textFile' => $textFile,
            'versions' => $versions,
        ]);
    }

    #[Route('/version/restore/{id}', name: 'version_restore')]
    public function restore(
        int $id,
        EntityManagerInterface $entityManager
    ): Response {
        $utilisateur = $this->getUser();

        $version = $entityManager->getRepository(TextFileVersion::class)->find($id);

        if (!$version) {
            $this->addFlash('error', 'Version introuvable.');
            return $this->redirectToRoute('repertoire');
        }

        $textFile = $version->getTextFileId();

        if ($textFile->getUtilisateurFile() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à restaurer ce fichier.');
        }

        $nouvelleVersion = new TextFileVersion();
        $nouvelleVersion->setBodyFile($textFile->getBodyFile());
        $nouvelleVersion->setDateEdition(new \DateTime());
        $nouvelleVersion->setTextFileId($textFile);
        $nouvelleVersion->setCommentaire('Sauvegarde automatique avant restauration de la version du ' . $version->getDateEdition()->format('d/m/Y H:i:s'));

        $entityManager->persist($nouvelleVersion);

        $textFile->setBodyFile($version->getBodyFile());

        $versionRestauration = new TextFileVersion();
        $versionRestauration->setBodyFile($version->getBodyFile());
        $versionRestauration->setDateEdition(new \DateTime());
        $versionRestauration->setTextFileId($textFile);
        $versionRestauration->setCommentaire('Restauration de la version du ' . $version->getDateEdition()->format('d/m/Y H:i:s'));

        $entityManager->persist($versionRestauration);
        $entityManager->flush();

        $this->addFlash('success', 'Le fichier "' . $textFile->getNameFile() . '" a été restauré avec succès.');

        return $this->redirectToRoute('repertoire');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/version/detail/{id}', name: 'version_detail')]
    public function detail(
        int $id,
        EntityManagerInterface $entityManager
    ): Response {
        $utilisateur = $this->getUser();

        $version = $entityManager->getRepository(TextFileVersion::class)->find($id);

        if (!$version) {
            throw $this->createNotFoundException('Version introuvable.');
        }

        $textFile = $version->getTextFileId();

        // Vérifier que l'utilisateur est le propriétaire du fichier
        if ($textFile->getUtilisateurFile() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à voir cette version.');
        }

        return $this->render('version/detail.html.twig', [
            'version' => $version,
            'textFile' => $textFile,
        ]);
    }
}
