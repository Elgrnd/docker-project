<?php

namespace App\Controller;

use App\Entity\YamlFile;
use App\Form\YamlFileType;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class YamlFileController extends AbstractController
{
    #[Route('/upload', name: 'yaml_upload', methods:['GET', 'POST'])]
    public function upload(Request $request, EntityManagerInterface $entityManager, FlashMessageHelperInterface $flashMessageHelper): Response
    {
        $utilisateur = $this->getUser();

        if ($utilisateur == null) {
            $this->addFlash('error', 'Vous devez être connecté pour importer un fichier');
            return $this->redirectToRoute('connexion');
        }

        $yamlFile = new YamlFile();

        $form = $this->createForm(YamlFileType::class, $yamlFile, [
            'method' => 'POST',
            'action' => $this->generateUrl('yaml_upload'),
        ]);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $uploadedFile = $form->get('yamlFile')->getData();

            $results = $entityManager
                ->getRepository(YamlFile::class)
                ->findByNomEtUtilisateur($yamlFile->getNameFile(), $utilisateur->getId());

            if (count($results) > 0) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà pour votre compte.',
                    $yamlFile->getNameFile()
                ));
                return $this->redirectToRoute('yaml_upload');
            }

            if ($uploadedFile) {
                try {
                    $content = file_get_contents($uploadedFile->getRealPath());
                    $yamlFile->setBodyFile($content);
                    $yamlFile->setIdUtilisateur($utilisateur->getId());

                    $entityManager->persist($yamlFile);
                    $entityManager->flush();

                    $this->addFlash('success', 'Fichier YAML importé avec succès.');
                    return $this->redirectToRoute('yaml_upload');
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors de la lecture du fichier');
                }
            }
        }
        else {
            $flashMessageHelper->addFormErrorsAsFlash($form);
        }

        return $this->render('YamlFile/upload.html.twig', ['formulaireYaml' => $form]);
    }
}
