<?php

namespace App\Controller;

use App\Entity\Groupe;
use App\Entity\GroupeYamlFileRepertoire;
use App\Entity\Repertoire;
use App\Entity\Utilisateur;
use App\Entity\UtilisateurYamlFileRepertoire;
use App\Entity\YamlFile;
use App\Form\DeplacerYamlFileGroupeType;
use App\Form\DirectoryGroupeType;
use App\Form\GroupeYamlFileRepertoireType;
use App\Form\PartagerYamlFileGroupeType;
use App\Repository\YamlFileRepository;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class GroupeYamlFileRepertoireController extends AbstractController
{
    #[IsGranted(attribute: 'GROUPE_VIEW', subject: 'groupe')]
    #[Route('/groupe/{id}/fichiers', name: 'fichiers_groupe', methods: ['GET', 'POST'])]
    public function fichiersGroupe(
        Groupe $groupe,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $repertoireRepository = $entityManager->getRepository(Repertoire::class);
        $gyrRepository = $entityManager->getRepository(GroupeYamlFileRepertoire::class);

        $repertoire = new Repertoire();
        $form = $this->createForm(DirectoryGroupeType::class, $repertoire, [
            'groupe' => $groupe,
        ]);
        $form->handleRequest($request);

        if ($this->isGranted('GROUPE_EDIT', $groupe) && $form->isSubmitted() && $form->isValid()) {
            // Associer l'utilisateur au répertoire
            $repertoire->setGroupeRepertoire($groupe);

            // Si aucun parent n'est sélectionné, utiliser le répertoire racine
            if ($repertoire->getParent() === null) {
                $repertoireRacine = $repertoireRepository->recupererRepertoireRacineGroupe($groupe->getId());

                if ($repertoireRacine) {
                    $repertoire->setParent($repertoireRacine);
                }
            }

            if($repertoireRepository->verifierNomDejaExistantGroupe($repertoire->getName(), $repertoire->getParent(), $groupe->getId()) != null){
                $this->addFlash('error', 'Un répertoire avec ce nom existe déjà à cet emplacement');
                return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
            }

            $entityManager->persist($repertoire);
            $entityManager->flush();

            $this->addFlash('success', 'Répertoire créé avec succès !');
            return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
        }

        // Récupérer le répertoire racine
        $repertoireRacine = $repertoireRepository->recupererRepertoireRacineGroupe($groupe->getId());

        // Récupérer tous les fichiers de l'utilisateur
        $listGyr = $gyrRepository->recuperertoutYamlfileGroupeParRepertoire($groupe->getId());

        return $this->render('yaml_file/listeYamlFileGroupe.html.twig', [
            'groupe' => $groupe,
            'listGyr' => $listGyr,
            'repertoireRacine' => $repertoireRacine,
            'formRepertoire' => $form
        ]);
    }

    #[IsGranted(attribute: 'GROUPE_VIEW', subject: 'groupe')]
    #[Route('/groupe/{id}/upload_fichier', name: 'upload_yaml_groupe', methods:  ['GET', 'POST'])]
    public function uploadYamlFileGroupe(
        Groupe $groupe,
        EntityManagerInterface $entityManager,
        Request $request,
        FlashMessageHelperInterface $flashMessageHelper,
    ) : Response {

        $utilisateur = $this->getUser();
        $repertoireRepository = $entityManager->getRepository(Repertoire::class);
        $gyrRepository = $entityManager->getRepository(GroupeYamlFileRepertoire::class);

        $gyr =  new GroupeYamlFileRepertoire();

        $formImport = $this->createForm(GroupeYamlFileRepertoireType::class, $gyr, [
            'groupe' => $groupe,
        ]);

        $formImport->handleRequest($request);

        if ($formImport->isSubmitted() && $formImport->isValid()) {
            $uploadedFile = $formImport->get('yamlFile')->getData();
            $droit = $formImport->get('droit')->getData();
            $repertoireId = $formImport->get('repertoire')->getData();

            if (!$uploadedFile) {
                $this->addFlash('error', 'Aucun fichier reçu.');
                return $this->redirectToRoute('upload_yaml_groupe', ['id' => $groupe->getId()]);
            }

            $nameFile = $uploadedFile->getClientOriginalName();
            $exists = $gyrRepository->existsYamlFileGroupe($groupe->getId(), $nameFile, $repertoireId);

            if ($exists) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà pour votre groupe dans ce répertoire.',
                    $nameFile
                ));
                return $this->redirectToRoute('upload_yaml_groupe', ['id' => $groupe->getId()]);
            }

            try {
                $yamlFile = new YamlFile();

                $extension = strtolower($uploadedFile->getClientOriginalExtension());
                $yamlFile->assertValidExtension($extension);

                $content = file_get_contents($uploadedFile->getRealPath());
                $yamlFile->assertNotEmpty($content);

                Yaml::parse($content);

                $repertoire = $repertoireRepository->find($repertoireId);

                $yamlFile->setNameFile($uploadedFile->getClientOriginalName());
                $yamlFile->setBodyFile($content);
                $yamlFile->setUtilisateurYamlfile($utilisateur);

                $gyr->setDroit($droit);
                $gyr->setGroupe($groupe);
                $gyr->setRepertoire($repertoire);
                $gyr->setYamlFile($yamlFile);

                $entityManager->persist($yamlFile);
                $entityManager->persist($gyr);
                $entityManager->flush();

                $repertoireNom = $repertoire->getFullPath();

                $this->addFlash('success', sprintf(
                    'Fichier "%s" ajouté avec succès au groupe "%s" dans "%s".',
                    $uploadedFile->getClientOriginalName(),
                    $groupe->getNom(),
                    $repertoireNom
                ));

                return $this->redirectToRoute('upload_yaml_groupe', ['id' => $groupe->getId()]);
            } catch (DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de la lecture du fichier YAML.');
            } catch(ParseException $e) {
                $this->addFlash('error', "La syntaxe du fichier n'est pas bonne " . $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue: ' . $e->getMessage());
            }
        }

        $flashMessageHelper->addFormErrorsAsFlash($formImport);

        return $this->render('yaml_file/upload_groupe.html.twig', [
            'formImport' => $formImport,
            'groupe' => $groupe,
        ]);
    }

    #[IsGranted(attribute: 'GROUPE_VIEW', subject: 'groupe')]
    #[Route('/groupe/{id}/partager_fichier', name: 'partager_yaml_groupe', methods:  ['GET', 'POST'])]
    public function partagerYamlGroupe(
        Groupe $groupe,
        Request $request,
        FlashMessageHelperInterface $flashMessageHelper,
        EntityManagerInterface $entityManager
    ) : Response
    {
        $utilisateur = $this->getUser();

        $yamlFilesUtilisateur = $entityManager
            ->getRepository(UtilisateurYamlFileRepertoire::class)
            ->findYamlFilesForUser($utilisateur);

        $yamlChoices = [];

        $gyrRepository = $entityManager->getRepository(GroupeYamlFileRepertoire::class);

        foreach ($yamlFilesUtilisateur as $uyfr) {

            $file = $uyfr->getYamlFile();

            $existant = $gyrRepository->findOneBy(['yamlFile' => $file, 'groupe' => $groupe]);

            if (!$existant) {
                $repertoire = $uyfr->getRepertoire();

                $displayName = sprintf(
                    "%s — %s",
                    $file->getNameFile(),
                    $repertoire->getFullPath()
                );

                $yamlChoices[$displayName] = $file->getId();
            }
        }

        $formExistant = $this->createForm(PartagerYamlFileGroupeType::class, null, [
            'yaml_choices' => $yamlChoices,
            'groupe' => $groupe,
        ]);

        $formExistant->handleRequest($request);

        if ($formExistant->isSubmitted() && $formExistant->isValid()) {

            $data = $formExistant->getData();
            $yamlId = $data['yamlId'];
            $droit = $data['droit'];
            $repertoireId = $formExistant->get('repertoire')->getData();

            $yamlFile = $entityManager->getRepository(YamlFile::class)->find($yamlId);

            if (!$yamlFile) {
                $this->addFlash('error', "Fichier introuvable.");
                return $this->redirectToRoute('partager_yaml_groupe', ['id' => $groupe->getId()]);
            }

            $exists = $gyrRepository->existsYamlFileGroupe($groupe->getId(), $yamlFile->getNameFile(), $repertoireId);

            if ($exists) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà pour votre groupe dans ce répertoire.',
                    $yamlFile->getNameFile()
                ));
                return $this->redirectToRoute('partager_yaml_groupe', ['id' => $groupe->getId()]);
            }

            $repertoireRepository = $entityManager->getRepository(Repertoire::class);
            $repertoire = $repertoireRepository->find($repertoireId);

            $gyr =  new GroupeYamlFileRepertoire();
            $gyr->setYamlFile($yamlFile);
            $gyr->setGroupe($groupe);
            $gyr->setDroit($droit);
            $gyr->setRepertoire($repertoire);

            $entityManager->persist($gyr);
            $entityManager->flush();

            $this->addFlash('success', 'Fichier partagé au groupe.');
            return $this->redirectToRoute('partager_yaml_groupe', ['id' => $groupe->getId()]);
        }

        $flashMessageHelper->addFormErrorsAsFlash($formExistant);

        return $this->render('yaml_file/partagerYamlFileGroupe.html.twig', [
            'groupe' => $groupe,
            'formExistant' => $formExistant,
        ]);
    }

    #[Route('/groupe/{id}/supprimer_fichier/{yamlId}', name: 'deleteYamlFileGroupe', options: ["expose" => true], methods: ['DELETE'])]
    public function supprimerYamlDuGroupe(
        Groupe $groupe,
        int $yamlId,
        EntityManagerInterface $entityManager,
        Request $request,
    ): Response {

        $yamlFile = $entityManager->getRepository(YamlFile::class)->findOneBy(['id' =>  $yamlId]);

        if (!$yamlFile) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $gyrRepository = $entityManager->getRepository(GroupeYamlFileRepertoire::class);

        $gyr = $gyrRepository->findByYamlFileAndGroupe($yamlFile->getId(), $groupe->getId());

        //Gestion droit avec un voter, pas avec IsGranted car GroupeYamlfileRepertoire possède plusieurs clés primaires, et symphony ne peut traiter ce cas avec IsGranted.
        $this->denyAccessUnlessGranted('GROUPE_FILE_DELETE', $gyr);

        $submittedToken = $request->getPayload()->get('_token');

        if (!$this->isCsrfTokenValid('delete' . $groupe->getId() . $yamlFile->getId(), $submittedToken)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $gyrRepository->supprimerYamlfileGroupeParRepertoire($yamlFile->getId());
        if ($entityManager->getRepository(UtilisateurYamlFileRepertoire::class)->findOneBy(['yamlFile' => $gyr->getYamlFile()]) === null) {
            $entityManager->remove($gyr->getYamlFile());
        }
        $entityManager->remove($gyr);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/groupe/{id}/modifier-fichier/{yamlId}', name: 'modifier_yaml_file_groupe', methods: ['GET', 'POST'])]
    public function modifierYamlFileGroupe(
        Groupe $groupe,
        int $yamlId,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {

        $gyr = $entityManager->getRepository(GroupeYamlFileRepertoire::class)->findByYamlFileAndGroupe($yamlId, $groupe->getId());

        //Gestion droit avec un voter, pas avec IsGranted car GroupeYamlfileRepertoire possède plusieurs clés primaires, et symphony ne peut traiter ce cas avec IsGranted.
        $this->denyAccessUnlessGranted('GROUPE_FILE_EDIT', $gyr);

        $yamlFile = $gyr->getYamlFile();

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_token');

            if ($this->isCsrfTokenValid('edit-yaml', $submittedToken)) {
                $yamlContent = $request->request->get('content');

                try {
                    Yaml::parse($yamlContent);
                    $yamlFile->setBodyFile($yamlContent);
                    $entityManager->flush();

                    $this->addFlash('success', 'Fichier YAML modifié avec succès.');
                    return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
                } catch (ParseException $e) {
                    $this->addFlash('error', 'Erreur dans le format YAML : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('error', 'Token CSRF invalide.');
            }
        }

        return $this->render('yaml_file/edityamlfilegroupe.html.twig', [
            'yamlfile' => $yamlFile,
            'groupe' => $groupe,
        ]);
    }

    #[IsGranted(attribute: 'GROUPE_VIEW', subject: 'groupe')]
    #[Route('/groupe/{id}/ajouter-yaml', name: 'ajouter_yaml_existant_groupe', methods: ['POST'])]
    public function ajouterYamlExistant(
        Request $request,
        Groupe $groupe,
        EntityManagerInterface $entityManager,
        YamlFileRepository $yamlFileRepository
    ): Response {
        $user = $this->getUser();
        $yamlId = $request->request->get('yamlId');
        $droit = $request->request->get('droit', 'lecture');

        $yamlFile = $yamlFileRepository->find($yamlId);
        if (!$yamlFile || $yamlFile->getUtilisateurYamlfile() !== $user) {
            $this->addFlash('error', "Fichier invalide ou non autorisé.");
            return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
        }

        $gyr = new GroupeYamlFileRepertoire();
        $gyr->setYamlFile($yamlFile);
        $gyr->setDroit($droit);
        $gyr->setGroupe($groupe);

        //ajouter ici le repertoire aussi
        $entityManager->persist($gyr);
        $entityManager->flush();

        $this->addFlash('success', 'Fichier ajouté au groupe avec succès.');
        return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
    }

    #[IsGranted(attribute: 'GROUPE_EDIT', subject: 'groupe')]
    #[Route('/groupe/{id}/yamlfile/deplacer/{idYamlFile}', name: 'yamlfile_deplacer_groupe', methods: ['GET', 'POST'])]
    public function deplacer_groupe(
        int $idYamlFile,
        Groupe $groupe,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(DeplacerYamlFileGroupeType::class, null, [
            'groupe' => $groupe,
        ]);
        $form->handleRequest($request);

        $yamlFile = $entityManager->getRepository(YamlFile::class)->find($idYamlFile);

        if (!$yamlFile) {
            $this->addFlash('error', "Fichier inconnu.");
            return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $repertoire = $form->get('repertoire')->getData();

            $gyr = $entityManager->getRepository(GroupeYamlFileRepertoire::class)
                ->findByYamlFileAndGroupe($idYamlFile, $groupe->getId());

            $gyr->setRepertoire($repertoire);
            $entityManager->flush();

            $this->addFlash('success', "Fichier déplacé avec succès !");
            return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
        }

        return $this->render('yaml_file/deplacer.html.twig', [
            'form' => $form->createView(),
            'yamlFile' => $yamlFile,
        ]);
    }
}
