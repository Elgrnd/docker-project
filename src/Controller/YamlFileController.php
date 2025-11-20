<?php

namespace App\Controller;

use App\Entity\Repertoire;
use App\Entity\Utilisateur;
use App\Entity\UtilisateurYamlFileRepertoire;
use App\Entity\YamlFile;
use App\Form\DirectoryType;
use App\Form\YamlFileType;
use App\Repository\RepertoireRepository;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\YamlFileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class YamlFileController extends AbstractController
{
    #[Route('/upload', name: 'yaml_upload', methods: ['GET', 'POST'])]
    public function upload(
        Request                     $request,
        EntityManagerInterface      $entityManager,
        FlashMessageHelperInterface $flashMessageHelperInterface,
    ): Response
    {
        $utilisateur = $this->getUser();
        $repertoireRepository = $entityManager->getRepository(Repertoire::class);

        if ($utilisateur === null) {
            $this->addFlash('error', 'Vous devez être connecté pour importer un fichier');
            return $this->redirectToRoute('connexion');
        }

        if (!$utilisateur instanceof \App\Entity\Utilisateur) {
            throw $this->createAccessDeniedException('Utilisateur non reconnu.');
        }

        // Créer une nouvelle instance de YamlFile
        $yamlFile = new YamlFile();

        $form = $this->createForm(YamlFileType::class, $yamlFile, [
            'method' => 'POST',
            'action' => $this->generateUrl('yaml_upload'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $repertoireId = $form->get('repertoire')->getData();
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


            // Vérifier si un fichier avec le même nom existe déjà pour cet utilisateur
            $results = $entityManager
                ->getRepository(UtilisateurYamlFileRepertoire::class)
                ->verifierSiYamlFileExiste($utilisateur->getId(), $nameFile, $repertoireId);

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

                // Définir le répertoire par défaut (racine) si disponible
                $repertoire = $repertoireRepository->find($repertoireId);

                // Le répertoire a déjà été défini par le formulaire via setRepertoire()
                $yamlFile->setNameFile($nameFile);
                $yamlFile->setBodyFile($content);
                $yamlFile->setUtilisateurYamlfile($utilisateur);

                $uyr = new UtilisateurYamlFileRepertoire();
                $uyr->setUtilisateur($utilisateur);
                $uyr->setRepertoire($repertoire);
                $uyr->setYamlFile($yamlFile);


                $entityManager->persist($uyr);
                $entityManager->persist($yamlFile);
                $entityManager->flush();

                $repertoireNom = $repertoire->getFullPath();

                $this->addFlash('success', sprintf(
                    'Fichier YAML "%s" importé avec succès dans "%s".',
                    $nameFile,
                    $repertoireNom
                ));

                return $this->redirectToRoute('yaml_upload');
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de la lecture du fichier: ' . $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue: ' . $e->getMessage());
            }
        }

        $flashMessageHelperInterface->addFormErrorsAsFlash($form);

        return $this->render('yaml_file/upload.html.twig', [
            'formulaireYaml' => $form
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/repertoire', name: 'repertoire', methods: ['GET', 'POST'])]
    public function afficherRepertoire(
        Request                $request,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $utilisateur = $this->getUser();
        assert($utilisateur instanceof Utilisateur);

        $repertoire = new Repertoire();
        $form = $this->createForm(DirectoryType::class, $repertoire);
        $form->handleRequest($request);

        $repertoireRepository = $entityManager->getRepository(Repertoire::class);
        $uyrRepository = $entityManager->getRepository(UtilisateurYamlFileRepertoire::class);

        if ($form->isSubmitted() && $form->isValid()) {
            // Associer l'utilisateur au répertoire
            $repertoire->setUtilisateurRepertoire($utilisateur);

            // Si aucun parent n'est sélectionné, utiliser le répertoire racine
            if ($repertoire->getParent() === null) {
                $repertoireRacine = $repertoireRepository->recupererRepertoireRacineUtilisateur($utilisateur->getId());

                if ($repertoireRacine) {
                    $repertoire->setParent($repertoireRacine);
                }
            }

            $entityManager->persist($repertoire);
            $entityManager->flush();

            $this->addFlash('success', 'Répertoire créé avec succès !');
            return $this->redirectToRoute('repertoire');
        }

        // Récupérer le répertoire racine
        $repertoireRacine = $repertoireRepository->recupererRepertoireRacineUtilisateur($utilisateur->getId());

        // Récupérer tous les fichiers de l'utilisateur
        $listUyr = $uyrRepository->recuperertoutYamlfileUtilisateurParRepertoire($utilisateur->getId());

        return $this->render('yaml_file/repertoirePerso.html.twig', [
            'listUyr' => $listUyr,
            'formRepertoire' => $form,
            'repertoireRacine' => $repertoireRacine,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/yamlfile/supprimer/{id}', name: 'deleteYamlFile', options: ["expose" => true], methods: ['DELETE'])]
    public function supprimerYamlFile(?YamlFile                   $yamlFile,
                                      Request                     $request,
                                      EntityManagerInterface      $entityManager,
                                      FlashMessageHelperInterface $flashMessageHelperInterface): Response
    {
        $utilisateur = $this->getUser();
        $uyrRepository = $entityManager->getRepository(UtilisateurYamlFileRepertoire::class);

        if (!$yamlFile) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        if ($yamlFile->getUtilisateurYamlfile() !== $utilisateur) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $submittedToken = $request->getPayload()->get('_token');

        if (!$this->isCsrfTokenValid('delete' . $yamlFile->getId(), $submittedToken)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $uyrRepository->supprimerYamlfileUtilisateurParRepertoire($yamlFile->getId());
        $entityManager->remove($yamlFile);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('yamlfile/modifier/{id}', name: 'modifierYamlFile', methods: ['GET', 'POST'])]
    public function modifierYamlFile(?YamlFile                   $yamlFile,
                                     Request                     $request,
                                     EntityManagerInterface      $entityManager,
                                     FlashMessageHelperInterface $flashMessageHelperInterface): Response
    {

        $utilisateur = $this->getUser();

        if (!$yamlFile) {
            $this->addFlash('error', "Ce fichier n'existe pas");
            return $this->redirectToRoute('repertoire');
        }

        if ($yamlFile->getUtilisateurYamlfile() !== $utilisateur) {
            $this->addFlash('error', "Vous ne pouvez pas modifier ce fichier");
            return $this->redirectToRoute('repertoire');
        }

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_token');

            // Vérification CSRF
            if ($this->isCsrfTokenValid('edit-yaml', $submittedToken)) {
                $yamlContent = $request->request->get('content');

                try {
                    Yaml::parse($yamlContent);

                    $yamlFile->setBodyFile($yamlContent);
                    $entityManager->flush();

                    $this->addFlash('success', 'Fichier YAML modifié avec succès');
                    return $this->redirectToRoute('repertoire');

                } catch (ParseException $e) {
                    $this->addFlash('error', 'Erreur de syntaxe YAML : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('error', 'Token CSRF invalide');
                return $this->redirectToRoute('repertoire');
            }
        }

        return $this->render('yaml_file/edityamlfile.html.twig', ['yamlfile' => $yamlFile]);

    }
}
