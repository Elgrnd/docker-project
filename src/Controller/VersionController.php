<?php

namespace App\Controller;

use App\Entity\YamlFile;
use App\Entity\YamlFileVersion;
use App\Repository\YamlFileRepository;
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

        // Récupérer le fichier YAML spécifique
        $yamlFile = $entityManager->getRepository(YamlFile::class)->find($id);

        // Vérifier que le fichier existe
        if (!$yamlFile) {
            $this->addFlash('error', 'Fichier introuvable.');
            return $this->redirectToRoute('app_yamlfile_index'); // ou votre route de liste de fichiers
        }

        // Vérifier que l'utilisateur est le propriétaire du fichier
        if ($yamlFile->getUtilisateurYamlfile() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à voir les versions de ce fichier.');
        }

        // Récupérer et trier les versions
        $versions = $yamlFile->getVersion()->toArray();

        // Trier les versions par date d'édition décroissante (plus récente en premier)
        usort($versions, function(YamlFileVersion $a, YamlFileVersion $b) {
            return $b->getDateEdition() <=> $a->getDateEdition();
        });

        return $this->render('version/listVersion.html.twig', [
            'yamlFile' => $yamlFile,
            'versions' => $versions,
        ]);
    }

    #[Route('/version/restore/{id}', name: 'version_restore')]
    public function restore(
        int $id,
        EntityManagerInterface $entityManager
    ): Response {
        $utilisateur = $this->getUser();

        // Récupérer la version à restaurer
        $version = $entityManager->getRepository(YamlFileVersion::class)->find($id);

        if (!$version) {
            $this->addFlash('error', 'Version introuvable.');
            return $this->redirectToRoute('listVersion');
        }

        $yamlFile = $version->getYamlFileId();

        // Vérifier que l'utilisateur est le propriétaire du fichier
        if ($yamlFile->getUtilisateurYamlfile() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à restaurer ce fichier.');
        }

        // Sauvegarder la version actuelle avant la restauration
        $nouvelleVersion = new YamlFileVersion();
        $nouvelleVersion->setBodyFile($yamlFile->getBodyFile());
        $nouvelleVersion->setDateEdition(new \DateTime());
        $nouvelleVersion->setYamlFileId($yamlFile);
        $nouvelleVersion->setCommentaire('Sauvegarde automatique avant restauration de la version du ' . $version->getDateEdition()->format('d/m/Y H:i:s'));

        $entityManager->persist($nouvelleVersion);

        // Restaurer le contenu de la version sélectionnée
        $yamlFile->setBodyFile($version->getBodyFile());

        // Créer une nouvelle version pour marquer la restauration
        $versionRestauration = new YamlFileVersion();
        $versionRestauration->setBodyFile($version->getBodyFile());
        $versionRestauration->setDateEdition(new \DateTime());
        $versionRestauration->setYamlFileId($yamlFile);
        $versionRestauration->setCommentaire('Restauration de la version du ' . $version->getDateEdition()->format('d/m/Y H:i:s'));

        $entityManager->persist($versionRestauration);
        $entityManager->flush();

        $this->addFlash('success', 'Le fichier "' . $yamlFile->getNameFile() . '" a été restauré avec succès.');

        return $this->redirectToRoute('listVersion');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/version/detail/{id}', name: 'version_detail')]
    public function detail(
        int $id,
        EntityManagerInterface $entityManager
    ): Response {
        $utilisateur = $this->getUser();

        $version = $entityManager->getRepository(YamlFileVersion::class)->find($id);

        if (!$version) {
            throw $this->createNotFoundException('Version introuvable.');
        }

        $yamlFile = $version->getYamlFileId();

        // Vérifier que l'utilisateur est le propriétaire du fichier
        if ($yamlFile->getUtilisateurYamlfile() !== $utilisateur) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à voir cette version.');
        }

        return $this->render('version/detail.html.twig', [
            'version' => $version,
            'yamlFile' => $yamlFile,
        ]);
    }
}
