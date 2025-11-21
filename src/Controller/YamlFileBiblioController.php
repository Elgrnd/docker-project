<?php

namespace App\Controller;

use App\Entity\UtilisateurYamlFileRepertoire;
use App\Entity\YamlFile;
use App\Form\AjouterBiblioRepertoireType;
use App\Form\YamlFileBiblioType;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class YamlFileBiblioController extends AbstractController
{

    #[Route('/bibliotheque', name: 'bibliotheque')]
    public function bibliotheque(EntityManagerInterface $entityManager): Response
    {
        $repository  = $entityManager->getRepository(YamlFile::class);
        $utilisateur = $this->getUser();

        if (!$utilisateur) {
            $this->addFlash("error", "Vous devez être connecté pour accéder à la bibliothèque");
            return $this->redirectToRoute('connexion');
        }

        $fichiers = $repository->recupererYamlFileSansUtilisateur();

        return $this->render('yaml_file_biblio/bibliotheque.html.twig', ["fichiers" => $fichiers]);
    }

    #[IsGranted("ROLE_PROF")]
    #[Route('/bibliotheque/upload', name: 'biblio_upload', methods: ['GET', 'POST'])]
    public function upload(
        Request $request,
        EntityManagerInterface $entityManager,
        FlashMessageHelperInterface $flashMessageHelperInterface,
    ): Response {

        $yamlFile = new YamlFile();

        $form = $this->createForm(YamlFileBiblioType::class, $yamlFile, [
            'method' => 'POST',
            'action' => $this->generateUrl('biblio_upload'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $uploadedFile = $form->get('yamlFile')->getData();

            if (!$uploadedFile) {
                $this->addFlash('error', 'Aucun fichier reçu.');
                return $this->redirectToRoute('biblio_upload');
            }

            $extension = strtolower($uploadedFile->getClientOriginalExtension());
            if (!in_array($extension, ['yaml', 'yml'])) {
                $this->addFlash('error', 'Seuls les fichiers .yaml ou .yml sont autorisés.');
                return $this->redirectToRoute('yaml_upload');
            }

            $nameFile = $uploadedFile->getClientOriginalName();


            // Vérifier si un fichier avec le même nom existe déjà
            $results = $entityManager
                ->getRepository(YamlFile::class)
                ->verifierSiYamlFileExisteBiblio($nameFile);

            if (count($results) > 0) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà.',
                    $nameFile
                ));
                return $this->redirectToRoute('biblio_upload');
            }

            try {
                $content = file_get_contents($uploadedFile->getRealPath());

                if (trim($content) === '') {
                    $this->addFlash('error', 'Le fichier YAML ne peut pas être vide');
                    return $this->redirectToRoute('yaml_upload');
                }

                // Le répertoire a déjà été défini par le formulaire via setRepertoire()
                $yamlFile->setNameFile($nameFile);
                $yamlFile->setBodyFile($content);

                $entityManager->persist($yamlFile);
                $entityManager->flush();

                $this->addFlash('success', sprintf(
                    'Fichier YAML "%s" importé avec succès.',
                    $nameFile,
                ));

                return $this->redirectToRoute('bibliotheque');
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de la lecture du fichier: ' . $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue: ' . $e->getMessage());
            }
        }
        $flashMessageHelperInterface->addFormErrorsAsFlash($form);

        return $this->render('yaml_file_biblio/uploadBiblio.html.twig', [
            'formulaireYaml' => $form
        ]);
    }

    #[IsGranted("ROLE_USER")]
    #[Route("/bibliotheque/ajouterAuRepertoire/{id}", name: 'ajouterAuRepertoire', methods: ['GET', 'POST'])]
    public function ajouterAuRepertoire(?YamlFile $yamlFile, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$yamlFile) {
            $this->addFlash("error", "Le fichier n'existe pas");
        }

        $form = $this->createForm(AjouterBiblioRepertoireType::class, null, [
            'method' => 'POST',
            'action' => $this->generateUrl('ajouterAuRepertoire', ['id' => $yamlFile->getId()]),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $repertoire = $data['repertoire'];

            // Récupérer l'utilisateur courant
            $utilisateur = $this->getUser();

            // Créer un nouveau YamlFile à partir du YamlFileBiblio
            $newYamlFile = new YamlFile();

            $newYamlFile->setNameFile($yamlFile->getNameFile());
            $newYamlFile->setBodyFile($yamlFile->getBodyFile());
            $newYamlFile->setUtilisateurYamlfile($utilisateur);

            $uyr = new UtilisateurYamlFileRepertoire();
            $uyr->setYamlFile($newYamlFile);
            $uyr->setRepertoire($repertoire);
            $uyr->setUtilisateur($utilisateur);


            $results = $entityManager
                ->getRepository(UtilisateurYamlFileRepertoire::class)
                ->verifierSiYamlFileExiste($utilisateur->getId(), $newYamlFile->getNameFile(), $repertoire);

            if (count($results) > 0) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà dans ce repertoire.',
                    $newYamlFile->getNameFile()
                ));
                return $this->redirectToRoute('bibliotheque');
            }

            $entityManager->persist($newYamlFile);
            $entityManager->persist($uyr);
            $entityManager->flush();

            $this->addFlash('success', 'Fichier ajouté à votre répertoire avec succès');

            return $this->redirectToRoute('bibliotheque');
        }

        return $this->render('yaml_file_biblio/ajouterAuRepertoire.html.twig', [
            'formulaire' => $form->createView(),
            'yamlFileBiblio' => $yamlFile
        ]);
    }


}
