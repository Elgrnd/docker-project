<?php

namespace App\Controller;

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
                ->findByNomEtUtilisateur($nameFile, $utilisateur->getUserIdentifier());

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
                $yamlFile->setLogin($utilisateur->getUserIdentifier());

                $entityManager->persist($yamlFile);
                $entityManager->flush();

                $this->addFlash('success', 'Fichier YAML importé avec succès.');
                return $this->redirectToRoute('yaml_upload');
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de la lecture du fichier');
            }
        }

        $flashMessageHelperInterface->addFormErrorsAsFlash($form);

        return $this->render('yaml_file/upload.html.twig', ['formulaireYaml' => $form]);
    }

    #[Route('/repertoire', name: 'repertoire', methods: ['GET'])]
    public function afficherRepertoire(YamlFileRepository $yamlFileRepository): Response {

        $yamlFiles = $yamlFileRepository->findByLogin($this->getUser()->getUserIdentifier());
        return $this->render('yaml_file/repertoirePerso.html.twig', ['yamlFiles' => $yamlFiles]);

    }
}
