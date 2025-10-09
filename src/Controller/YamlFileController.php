<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\YamlFile;
use App\Form\YamlFileType;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\YamlFileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class YamlFileController extends AbstractController
{
    #[Route('/upload', name: 'yaml_upload', methods:['GET', 'POST'])]
    public function upload(Request $request, EntityManagerInterface $entityManager, FlashMessageHelperInterface $flashMessageHelperInterface): Response
    {
        $utilisateur = $this->getUser();

        if ($utilisateur === null) {
            $this->addFlash('error', 'Vous devez être connecté pour importer un fichier');
            return $this->redirectToRoute('connexion');
        }

        $form = $this->createForm(YamlFileType::class, null, [
            'method' => 'POST',
            'action' => $this->generateUrl('yaml_upload'),
        ]);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $uploadedFile = $form->get('yamlFile')->getData();

            if (!$uploadedFile) {
                $this->addFlash('error', 'Aucun fichier reçu.');
                return $this->redirectToRoute('yaml_upload');
            }

            $extension = strtolower($uploadedFile->getClientOriginalExtension());
            if (!in_array($extension, ['yaml', 'yml'])) {
                $this->addFlash('error', 'Seuls les fichiers .yaml ou .yml sont autorisés.');
                return $this->redirectToRoute('yaml_upload');
            }

            $nameFile = $uploadedFile->getClientOriginalName();

            $results = $entityManager
                ->getRepository(YamlFile::class)
                ->findByNomEtUtilisateur($nameFile, $this->getUser());

            if (count($results) > 0) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà pour votre compte.',
                    $nameFile
                ));
                return $this->redirectToRoute('yaml_upload');
            }

            try {
                $content = file_get_contents($uploadedFile->getRealPath());

                if (trim($content) === '') {
                    $this->addFlash('error', 'Le fichier YAML ne peut pas être vide');
                    return $this->redirectToRoute('yaml_upload');
                }

                $yamlFile = new YamlFile();
                $yamlFile->setNameFile($nameFile);
                $yamlFile->setBodyFile($content);
                $yamlFile->setUtilisateur($this->getUser());

                $entityManager->persist($yamlFile);
                $entityManager->flush();

                $this->addFlash('success', 'Fichier YAML importé avec succès.');

                return $this->redirectToRoute('yaml_upload');
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de la lecture du fichier', $e);
            }
        }

        $flashMessageHelperInterface->addFormErrorsAsFlash($form);

        return $this->render('yaml_file/upload.html.twig', ['formulaireYaml' => $form]);
    }

    #[Route('/repertoire', name: 'repertoire', methods: ['GET'])]
    public function afficherRepertoire(YamlFileRepository $yamlFileRepository): Response {

        $utilisateur = $this->getUser();
        assert($utilisateur instanceof Utilisateur);

        $yamlFiles = $yamlFileRepository->findByUtilisateur($utilisateur);

        return $this->render('yaml_file/repertoirePerso.html.twig', [
            'yamlFiles' => $yamlFiles
        ]);
    }

    #[Route('/yamlfile/{id}/supprimer', name: 'delete', methods: ['POST'])]
    public function supprimerYamlFile(YamlFile $yamlFile, Request $request, EntityManagerInterface $entityManager, FlashMessageHelperInterface $flashMessageHelperInterface): Response
    {
        $utilisateur = $this->getUser();

        if ($utilisateur === null) {
            $this->addFlash('error', 'Vous devez être connecté pour supprimer un fichier');
            return $this->redirectToRoute('connexion');
        }

        if ($yamlFile->getUtilisateur() !== $utilisateur) {
            $this->addFlash("error", "Ce fichier ne vous appartient pas");
            return $this->redirectToRoute('repertoire');
        }

        if (!$this->isCsrfTokenValid('delete'.$yamlFile->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('repertoire');
        }

        $entityManager->remove($yamlFile);
        $entityManager->flush();

        $this->addFlash("success", "Fichier supprimé avec succès");
        return $this->redirectToRoute('repertoire');
    }
}
