<?php

namespace App\Controller;

use App\Entity\TextFile;
use App\Entity\TextFileVersion;
use App\Entity\UtilisateurFileRepertoire;
use App\Form\AjouterBiblioRepertoireType;
use App\Form\TextFileBiblioType;
use App\Service\FileUploadService;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class TextFileController extends AbstractController
{
    #[IsGranted('FILE_EDIT', subject: 'textFile')]
    #[Route('textfile/modifier/{id}', name: 'modifierTextFile', methods: ['GET', 'POST'])]
    public function modifierTextFile(
        ?TextFile $textFile,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$textFile) {
            $this->addFlash('error', "Ce fichier n'existe pas");
            return $this->redirectToRoute('repertoire');
        }

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_token');

            if ($this->isCsrfTokenValid('edit-text', $submittedToken)) {
                $textContent = (string) $request->request->get('text_content');
                $description = $request->request->get('description');

                try {
                    if ($textFile->isYaml()) {
                        Yaml::parse($textContent);
                    }

                    $version = new TextFileVersion();
                    $version->setBodyFile($textFile->getBodyFile());
                    $version->setTextFileId($textFile);
                    $version->setDateEdition(new \DateTime());
                    $version->setUtilisateur($this->getUser());
                    $version->setCommentaire('Sauvegarde automatique avant modification du ' . (new \DateTime())->format('d/m/Y H:i:s'));
                    $entityManager->persist($version);

                    $textFile->setBodyFile($textContent);

                    if ($description !== null) {
                        $textFile->setDescription($description);
                    }

                    $textFile->addVersion($version);

                    $entityManager->flush();

                    $this->addFlash('success', 'Fichier modifié avec succès');
                    return $this->redirectToRoute('repertoire');

                } catch (ParseException $e) {
                    $this->addFlash('error', 'Erreur de syntaxe YAML : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('error', 'Token CSRF invalide');
                return $this->redirectToRoute('repertoire');
            }
        }

        return $this->render('file/editTextFile.html.twig', ['textFile' => $textFile]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/bibliotheque', name: 'bibliotheque')]
    public function bibliotheque(EntityManagerInterface $entityManager): Response
    {
        $repository = $entityManager->getRepository(TextFile::class);
        $fichiers = $repository->recupererTextFileSansUtilisateur();

        return $this->render('file/bibliotheque.html.twig', ["fichiers" => $fichiers]);
    }

    #[IsGranted("ROLE_PROFESSEUR")]
    #[Route('/bibliotheque/upload', name: 'biblio_upload', methods: ['GET', 'POST'])]
    public function uploadBiblio(
        Request $request,
        EntityManagerInterface $entityManager,
        FlashMessageHelperInterface $flashMessageHelperInterface,
        FileUploadService $fileUploadService,
    ): Response {
        $form = $this->createForm(TextFileBiblioType::class, null, [
            'method' => 'POST',
            'action' => $this->generateUrl('biblio_upload'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $uploadedFile = $form->get('file')->getData();
            if (!$uploadedFile) {
                $this->addFlash('error', 'Aucun fichier reçu.');
                return $this->redirectToRoute('biblio_upload');
            }

            $nameFile = $uploadedFile->getClientOriginalName();

            $repo = $entityManager->getRepository(TextFile::class);
            if ($repo->existeDansBiblio($nameFile)) {
                $this->addFlash('error', sprintf('Un fichier nommé "%s" existe déjà.', $nameFile));
                return $this->redirectToRoute('biblio_upload');
            }

            try {
                $file = $fileUploadService->createFromUploadedFile(
                    $uploadedFile,
                    description: null,
                    validateYamlWhenYaml: true
                );

                if (!$file instanceof TextFile) {
                    throw new DomainException("La bibliothèque accepte uniquement des fichiers texte.");
                }

                $entityManager->persist($file);
                $entityManager->flush();

                $this->addFlash('success', sprintf('Fichier "%s" importé avec succès.', $nameFile));
                return $this->redirectToRoute('bibliotheque');

            } catch (ParseException $e) {
                $this->addFlash('error', "YAML invalide : " . $e->getMessage());
                return $this->redirectToRoute('biblio_upload');
            } catch (DomainException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('biblio_upload');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Une erreur est survenue: ' . $e->getMessage());
                return $this->redirectToRoute('biblio_upload');
            }
        }

        $flashMessageHelperInterface->addFormErrorsAsFlash($form);

        return $this->render('file/uploadBiblio.html.twig', [
            'formulaireFile' => $form->createView(),
        ]);
    }

    #[IsGranted("FILE_BIBLIO", subject: 'textFile')]
    #[Route("/bibliotheque/ajouterAuRepertoire/{id}", name: 'ajouterAuRepertoire', methods: ['GET', 'POST'])]
    public function ajouterAuRepertoire(
        ?TextFile $textFile,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$textFile) {
            $this->addFlash("error", "Le fichier n'existe pas");
            return $this->redirectToRoute('bibliotheque');
        }

        $form = $this->createForm(AjouterBiblioRepertoireType::class, null, [
            'method' => 'POST',
            'action' => $this->generateUrl('ajouterAuRepertoire', ['id' => $textFile->getId()]),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $repertoire = $data['repertoire'];

            $utilisateur = $this->getUser();

            $newTextFile = new TextFile();
            $newTextFile->setNameFile($textFile->getNameFile());
            $newTextFile->setBodyFile($textFile->getBodyFile());
            $newTextFile->setUtilisateurFile($utilisateur);

            $ufr = new UtilisateurFileRepertoire();
            $ufr->setFile($newTextFile);
            $ufr->setRepertoire($repertoire);
            $ufr->setUtilisateur($utilisateur);

            $repo = $entityManager->getRepository(UtilisateurFileRepertoire::class);

            if ($repo->existsFileUtilisateur($utilisateur->getId(), $newTextFile->getNameFile(), $repertoire->getId())) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà dans ce répertoire.',
                    $newTextFile->getNameFile()
                ));
                return $this->redirectToRoute('ajouterAuRepertoire', ['id' => $textFile->getId()]);
            }

            $entityManager->persist($newTextFile);
            $entityManager->persist($ufr);
            $entityManager->flush();

            $this->addFlash('success', 'Fichier ajouté à votre répertoire avec succès');
            return $this->redirectToRoute('repertoire');
        }

        return $this->render('file/ajouterAuRepertoire.html.twig', [
            'formulaire' => $form->createView(),
            'fileBiblio' => $textFile,
            'routeAnnuler' => 'bibliotheque'
        ]);
    }
}
