<?php

namespace App\Controller;

use App\Entity\GroupeYamlFileRepertoire;
use App\Entity\Repertoire;
use App\Entity\Utilisateur;
use App\Entity\UtilisateurYamlFileRepertoire;
use App\Entity\YamlFile;
use App\Entity\YamlFileVersion;
use App\Form\AjouterBiblioRepertoireType;
use App\Form\DeplacerYamlFileType;
use App\Form\DirectoryType;
use App\Form\YamlFileBiblioType;
use App\Form\YamlFileType;
use App\Repository\RepertoireRepository;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
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
use ZipArchive;

final class YamlFileController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/upload', name: 'yaml_upload', methods: ['GET', 'POST'])]
    public function upload(
        Request                     $request,
        EntityManagerInterface      $entityManager,
        FlashMessageHelperInterface $flashMessageHelperInterface,
    ): Response
    {
        $utilisateur = $this->getUser();
        $repertoireRepository = $entityManager->getRepository(Repertoire::class);

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

            $nameFile = $uploadedFile->getClientOriginalName();
            $exists = $entityManager
                ->getRepository(UtilisateurYamlFileRepertoire::class)
                ->existsYamlFileUtilisateur($utilisateur->getId(), $nameFile, $repertoireId);

            if ($exists) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà pour votre compte dans ce répertoire.',
                    $nameFile
                ));
                return $this->redirectToRoute('yaml_upload');
            }


            try {
                $extension = strtolower($uploadedFile->getClientOriginalExtension());
                $yamlFile->assertValidExtension($extension);

                $content = file_get_contents($uploadedFile->getRealPath());
                $yamlFile->assertNotEmpty($content);

                Yaml::parse($content);

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
            } catch (DomainException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('yaml_upload');
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de la lecture du fichier: ' . $e->getMessage());
            } catch(ParseException $e) {
                $this->addFlash('error', "La syntaxe du fichier n'est pas bonne " . $e->getMessage());
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

            if($repertoireRepository->verifierNomDejaExistant($repertoire->getName(), $repertoire->getParent(), $utilisateur->getId()) != null){
                $this->addFlash('error', 'Un répertoire avec ce nom existe déjà à cet emplacement');
                return $this->redirectToRoute('repertoire');
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

        return $this->render('repertoire/repertoirePerso.html.twig', [
            'listUyr' => $listUyr,
            'formRepertoire' => $form,
            'repertoireRacine' => $repertoireRacine,
        ]);
    }

    #[IsGranted('FILE_OWNER', subject: 'yamlFile')]
    #[Route('/yamlfile/supprimer/{id}', name: 'deleteYamlFile', options: ["expose" => true], methods: ['DELETE'])]
    public function supprimerYamlFile(?YamlFile                   $yamlFile,
                                      Request                     $request,
                                      EntityManagerInterface      $entityManager): Response
    {
        $utilisateur = $this->getUser();
        $uyrRepository = $entityManager->getRepository(UtilisateurYamlFileRepertoire::class);

        if (!$yamlFile) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $submittedToken = $request->getPayload()->get('_token');

        if (!$this->isCsrfTokenValid('delete' . $yamlFile->getId(), $submittedToken)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $uyrRepository->supprimerYamlfileUtilisateurParRepertoire($yamlFile->getId());
        if ($entityManager->getRepository(GroupeYamlFileRepertoire::class)->findOneBy(['yamlFile' => $yamlFile]) === null) {
            $entityManager->remove($yamlFile);
        }
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[IsGranted('FILE_OWNER', subject: 'yamlFile')]
    #[Route('yamlfile/modifier/{id}', name: 'modifierYamlFile', methods: ['GET', 'POST'])]
    public function modifierYamlFile(?YamlFile                   $yamlFile,
                                     Request                     $request,
                                     EntityManagerInterface      $entityManager): Response
    {

        $utilisateur = $this->getUser();

        if (!$yamlFile) {
            $this->addFlash('error', "Ce fichier n'existe pas");
            return $this->redirectToRoute('repertoire');
        }

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_token');

            // Vérification CSRF
            if ($this->isCsrfTokenValid('edit-yaml', $submittedToken)) {
                $yamlContent = $request->request->get('content');
                $description = $request->request->get('description');

                try {
                    Yaml::parse($yamlContent);

                    $version = new YamlFileVersion();
                    $version->setBodyFile($yamlFile->getBodyFile());
                    $version->setYamlFileId($yamlFile);
                    $version->setDateEdition(new \DateTime());
                    $entityManager->persist($version);

                    $yamlFile->setBodyFile($yamlContent);

                    if ($description !== null) {
                        $yamlFile->setDescription($description);
                    }

                    $yamlFile->addVersion($version);

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

    #[IsGranted('ROLE_USER')]
    #[Route('/bibliotheque', name: 'bibliotheque')]
    public function bibliotheque(EntityManagerInterface $entityManager): Response
    {
        $repository  = $entityManager->getRepository(YamlFile::class);
        $utilisateur = $this->getUser();

        $fichiers = $repository->recupererYamlFileSansUtilisateur();

        return $this->render('yaml_file/bibliotheque.html.twig', ["fichiers" => $fichiers]);
    }

    #[IsGranted("ROLE_PROF")]
    #[Route('/bibliotheque/upload', name: 'biblio_upload', methods: ['GET', 'POST'])]
    public function upload_biblio(
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

            $nameFile = $uploadedFile->getClientOriginalName();

            $repo = $entityManager->getRepository(YamlFile::class);

            if ($repo->existeDansBiblio($nameFile)) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà.',
                    $nameFile
                ));
                return $this->redirectToRoute('biblio_upload');
            }


            try {
                $extension = strtolower($uploadedFile->getClientOriginalExtension());
                $yamlFile->assertValidExtension($extension);

                $content = file_get_contents($uploadedFile->getRealPath());

                $content = file_get_contents($uploadedFile->getRealPath());
                $yamlFile->assertNotEmpty($content);

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
            } catch (DomainException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('yaml_upload');
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de la lecture du fichier: ' . $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue: ' . $e->getMessage());
            }
        }
        $flashMessageHelperInterface->addFormErrorsAsFlash($form);

        return $this->render('yaml_file/uploadBiblio.html.twig', [
            'formulaireYaml' => $form
        ]);
    }

    #[IsGranted("FILE_BIBLIO", subject: 'yamlFile')]
    #[Route("/bibliotheque/ajouterAuRepertoire/{id}", name: 'ajouterAuRepertoire', methods: ['GET', 'POST'])]
    public function ajouterAuRepertoire(?YamlFile $yamlFile, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$yamlFile) {
            $this->addFlash("error", "Le fichier n'existe pas");
            return $this->redirectToRoute('bibliotheque');
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


            $repo = $entityManager->getRepository(UtilisateurYamlFileRepertoire::class);

            if ($repo->existsYamlFileUtilisateur($utilisateur->getId(), $newYamlFile->getNameFile(), $repertoire)) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà dans ce répertoire.',
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

        $routeAnnuler = 'bibliotheque';

        return $this->render('gitlab/ajouterAuRepertoire.html.twig', [
            'formulaire' => $form->createView(),
            'yamlFileBiblio' => $yamlFile,
            'routeAnnuler' => $routeAnnuler
        ]);
    }

    #[IsGranted("FILE_OWNER", subject: 'yamlFile')]
    #[Route('/yamlfile/deplacer/{id}', name: 'yamlfile_deplacer', methods: ['GET', 'POST'])]
    public function deplacer(
        YamlFile $yamlFile,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $utilisateur = $this->getUser();

        $form = $this->createForm(DeplacerYamlFileType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $repertoire = $form->get('repertoire')->getData();

            $uyr = $entityManager->getRepository(UtilisateurYamlFileRepertoire::class)
                ->findOneBy(['yamlFile' => $yamlFile, 'utilisateur' => $utilisateur]);

            $uyr->setRepertoire($repertoire);
            $entityManager->flush();

            $this->addFlash('success', "Fichier déplacé avec succès !");
            return $this->redirectToRoute('repertoire');
        }

        return $this->render('yaml_file/deplacer.html.twig', [
            'form' => $form->createView(),
            'yamlFile' => $yamlFile,
        ]);
    }
}