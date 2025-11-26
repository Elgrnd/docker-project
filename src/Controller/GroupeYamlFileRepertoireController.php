<?php

namespace App\Controller;

use App\Entity\Groupe;
use App\Entity\GroupeYamlFileRepertoire;
use App\Entity\Utilisateur;
use App\Entity\UtilisateurYamlFileRepertoire;
use App\Entity\YamlFile;
use App\Form\GroupeYamlFileRepertoireType;
use App\Form\PartagerYamlFileGroupeType;
use App\Repository\YamlFileRepository;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
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
        FlashMessageHelperInterface $flashMessageHelper
    ): Response {
        $utilisateur = $this->getUser();

        $formImport = $this->createForm(GroupeYamlFileRepertoireType::class);
        $formImport->handleRequest($request);

        if ($formImport->isSubmitted() && $formImport->isValid()) {
            $uploadedFile = $formImport->get('yamlFile')->getData();
            $droit = $formImport->get('droit')->getData();

            if (!$uploadedFile) {
                $this->addFlash('error', 'Aucun fichier reçu.');
                return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
            }

            try {
                $yamlFile = new YamlFile();

                $extension = strtolower($uploadedFile->getClientOriginalExtension());
                $yamlFile->assertValidExtension($extension);

                $content = file_get_contents($uploadedFile->getRealPath());
                $yamlFile->assertNotEmpty($content);

                $yamlFile->setNameFile($uploadedFile->getClientOriginalName());
                $yamlFile->setBodyFile($content);
                $yamlFile->setUtilisateurYamlfile($utilisateur);

                // Création du YamlFileGroupe directement
                $gyr = new GroupeYamlFileRepertoire();

                $gyr->setDroit($droit);
                $gyr->setGroupe($groupe);
                $gyr->setYamlFile($yamlFile);
//                $gyr->setRepertoire(null);

                $entityManager->persist($yamlFile);
                $entityManager->persist($gyr);
                $entityManager->flush();

                $this->addFlash('success', sprintf(
                    'Fichier "%s" ajouté avec succès au groupe "%s".',
                    $uploadedFile->getClientOriginalName(),
                    $groupe->getNom()
                ));

                return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
            } catch (DomainException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('yaml_upload');
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de la lecture du fichier YAML.');
            }
        }

        $flashMessageHelper->addFormErrorsAsFlash($formImport);

        $yamlFilesUtilisateur = $entityManager
            ->getRepository(UtilisateurYamlFileRepertoire::class)
            ->findYamlFilesForUser($utilisateur);

        $yamlChoices = [];

        foreach ($yamlFilesUtilisateur as $uyfr) {

            $file = $uyfr->getYamlFile();

            $existant = $entityManager->getRepository(GroupeYamlFileRepertoire::class)
                ->findOneBy(['yamlFile' => $file, 'groupe' => $groupe]);

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
        ]);

        $formExistant->handleRequest($request);

        if ($formExistant->isSubmitted() && $formExistant->isValid()) {

            $data = $formExistant->getData();
            $yamlId = $data['yamlId'];
            $droit = $data['droit'];

            $yamlFile = $entityManager->getRepository(YamlFile::class)->find($yamlId);

            if (!$yamlFile) {
                $this->addFlash('error', "Fichier introuvable.");
                return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
            }

            $existant = $entityManager->getRepository(GroupeYamlFileRepertoire::class)
                ->findOneBy(['yamlFile' => $yamlFile, 'groupe' => $groupe]);

            if ($existant) {
                $this->addFlash('error', 'Ce fichier est déjà dans le groupe.');
                return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
            }


            $gyr = new GroupeYamlFileRepertoire();
            $gyr->setYamlFile($yamlFile);
            $gyr->setGroupe($groupe);
            $gyr->setDroit($droit);

            $entityManager->persist($gyr);
            $entityManager->flush();

            $this->addFlash('success', 'Fichier partagé au groupe.');
            return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
        }


        $fichiersGroupe = $entityManager
            ->getRepository(GroupeYamlFileRepertoire::class)
            ->findBy(['groupe' => $groupe]);

        return $this->render('yaml_file/listeYamlFileGroupe.html.twig', [
            'groupe' => $groupe,
            'fichiersGroupe' => $fichiersGroupe,
            'formImport' => $formImport,
            'formExistant' => $formExistant,
        ]);
    }

    #[Route('/groupe/{id}/supprimer-fichier/{yamlId}', name: 'supprimer_yaml_groupe', methods: ['POST'])]
    public function supprimerYamlDuGroupe(
        Groupe $groupe,
        int $yamlId,
        EntityManagerInterface $entityManager
    ): Response {

        $gyr = $entityManager->getRepository(GroupeYamlFileRepertoire::class)->findByYamlFileAndGroupe($yamlId, $groupe->getId());

        //Gestion droit avec un voter, pas avec IsGranted car GroupeYamlfileRepertoire possède plusieurs clés primaires, et symphony ne peut traiter ce cas avec IsGranted.
        $this->denyAccessUnlessGranted('GROUPE_FILE_DELETE', $gyr);

        $entityManager->remove($gyr);
        $entityManager->flush();

        $this->addFlash('success', 'Fichier supprimé du groupe.');
        return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
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
}
