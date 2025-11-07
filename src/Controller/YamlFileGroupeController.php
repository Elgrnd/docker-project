<?php

namespace App\Controller;

use App\Entity\YamlFile;
use App\Entity\YamlFileGroupe;
use App\Entity\Groupe;
use App\Form\YamlFileGroupeType;
use App\Repository\YamlFileRepository;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class YamlFileGroupeController extends AbstractController
{
    #[Route('/groupe/{id}/fichiers', name: 'fichiers_groupe', methods: ['GET', 'POST'])]
    public function fichiersGroupe(
        Groupe $groupe,
        Request $request,
        EntityManagerInterface $entityManager,
        FlashMessageHelperInterface $flashMessageHelper
    ): Response {
        $utilisateur = $this->getUser();
        if (!$utilisateur) {
            $this->addFlash('error', 'Veuillez vous connecter.');
            return $this->redirectToRoute('connexion');
        }

        $form = $this->createForm(YamlFileGroupeType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('yamlFile')->getData();
            $droit = $form->get('droit')->getData();

            if (!$uploadedFile) {
                $this->addFlash('error', 'Aucun fichier reçu.');
                return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
            }

            $extension = strtolower($uploadedFile->getClientOriginalExtension());
            if (!in_array($extension, ['yaml', 'yml'])) {
                $this->addFlash('error', 'Seuls les fichiers .yaml ou .yml sont autorisés.');
                return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
            }

            try {
                $content = file_get_contents($uploadedFile->getRealPath());
                if (trim($content) === '') {
                    $this->addFlash('error', 'Le fichier YAML ne peut pas être vide.');
                    return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
                }

                // Création du YamlFileGroupe directement
                $yamlFileGroupe = new YamlFileGroupe();
                $yamlFileGroupe->setNameFile($uploadedFile->getClientOriginalName());
                $yamlFileGroupe->setBodyFile($content);
                $yamlFileGroupe->setDroit($droit);
                $yamlFileGroupe->setGroupe($groupe);

                $entityManager->persist($yamlFileGroupe);
                $entityManager->flush();

                $this->addFlash('success', sprintf(
                    'Fichier "%s" ajouté avec succès au groupe "%s".',
                    $uploadedFile->getClientOriginalName(),
                    $groupe->getNom()
                ));

                return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de la lecture du fichier YAML.');
            }
        }

        $flashMessageHelper->addFormErrorsAsFlash($form);

        $fichiersGroupe = $entityManager
            ->getRepository(YamlFileGroupe::class)
            ->findBy(['groupe' => $groupe]);

        $yamlFilesUtilisateur = $entityManager->createQueryBuilder()
            ->select('y')
            ->from(YamlFile::class, 'y')
            ->where('y.utilisateur = :utilisateur')
            ->setParameter('utilisateur', $utilisateur->getId()) // ⚠️ on passe l'ID, pas l'objet complet
            ->getQuery()
            ->getResult();


        return $this->render('yaml_file_groupe/PartagerYamlFileGroupe.html.twig', [
            'groupe' => $groupe,
            'fichiersGroupe' => $fichiersGroupe,
            'formImport' => $form,
            'yamlFilesUtilisateur' => $yamlFilesUtilisateur,
        ]);
    }

    #[Route('/groupe/{id}/supprimer-fichier/{yamlId}', name: 'supprimer_yaml_groupe', methods: ['POST'])]
    public function supprimerYamlDuGroupe(
        Groupe $groupe,
        int $yamlId,
        EntityManagerInterface $entityManager
    ): Response {
        $utilisateur = $this->getUser();
        if (!$utilisateur) {
            $this->addFlash('error', 'Veuillez vous connecter.');
            return $this->redirectToRoute('connexion');
        }

        $yamlFileGroupe = $entityManager->getRepository(YamlFileGroupe::class)->find($yamlId);

        if (!$yamlFileGroupe || $yamlFileGroupe->getGroupe() !== $groupe) {
            $this->addFlash('error', 'Fichier introuvable.');
            return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
        }

        // Droits : seuls chef ou admin peuvent tout supprimer
        if (
            !$this->isGranted('ROLE_ADMIN') &&
            $groupe->getUtilisateurChef() !== $utilisateur
        ) {
            $this->addFlash('error', 'Vous n’êtes pas autorisé à supprimer ce fichier.');
            return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
        }

        $entityManager->remove($yamlFileGroupe);
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
        $utilisateur = $this->getUser();

        $yamlFileGroupe = $entityManager->getRepository(YamlFileGroupe::class)->find($yamlId);

        if (!$yamlFileGroupe || $yamlFileGroupe->getGroupe() !== $groupe) {
            $this->addFlash('error', 'Fichier introuvable dans ce groupe.');
            return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
        }

        // Droits
        if (
            !$this->isGranted('ROLE_ADMIN') &&
            $groupe->getUtilisateurChef() !== $utilisateur &&
            $yamlFileGroupe->getDroit() !== 'edition'
        ) {
            $this->addFlash('error', "Vous n’avez pas les droits pour modifier ce fichier.");
            return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
        }

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_token');

            if ($this->isCsrfTokenValid('edit-yaml', $submittedToken)) {
                $yamlContent = $request->request->get('content');

                try {
                    Yaml::parse($yamlContent);
                    $yamlFileGroupe->setBodyFile($yamlContent);
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

        return $this->render('yaml_file_groupe/edityamlfilegroupe.html.twig', [
            'yamlfile' => $yamlFileGroupe,
            'groupe' => $groupe,
        ]);
    }

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
        if (!$yamlFile || $yamlFile->getUtilisateur() !== $user) {
            $this->addFlash('error', "Fichier invalide ou non autorisé.");
            return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
        }

        $yamlFileGroupe = new YamlFileGroupe();
        $yamlFileGroupe->setNameFile($yamlFile->getNameFile());
        $yamlFileGroupe->setBodyFile($yamlFile->getBodyFile());
        $yamlFileGroupe->setDroit($droit);
        $yamlFileGroupe->setGroupe($groupe);

        $entityManager->persist($yamlFileGroupe);
        $entityManager->flush();

        $this->addFlash('success', 'Fichier ajouté au groupe avec succès.');
        return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
    }
}
