<?php

namespace App\Controller;

use App\Entity\BinaryFile;
use App\Entity\File;
use App\Entity\Groupe;
use App\Entity\GroupeFileRepertoire;
use App\Entity\Repertoire;
use App\Entity\TextFileVersion;
use App\Entity\UtilisateurFileRepertoire;
use App\Entity\TextFile;
use App\Form\DeplacerFileGroupeType;
use App\Form\DirectoryGroupeType;
use App\Form\GroupeFileRepertoireType;
use App\Form\PartagerFileGroupeType;
use App\Repository\FileRepository;
use App\Service\FileUploadService;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class GroupeFileRepertoireController extends AbstractController
{
    #[IsGranted(attribute: 'GROUPE_VIEW', subject: 'groupe')]
    #[Route('/groupe/{id}/fichiers', name: 'fichiers_groupe', methods: ['GET', 'POST'])]
    public function fichiersGroupe(
        Groupe $groupe,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $repertoireRepository = $entityManager->getRepository(Repertoire::class);
        $gfrRepository = $entityManager->getRepository(GroupeFileRepertoire::class);

        $repertoire = new Repertoire();
        $form = $this->createForm(DirectoryGroupeType::class, $repertoire, [
            'groupe' => $groupe,
        ]);
        $form->handleRequest($request);

        if ($this->isGranted('GROUPE_EDIT', $groupe) && $form->isSubmitted() && $form->isValid()) {
            $repertoire->setGroupeRepertoire($groupe);

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

        $repertoireRacine = $repertoireRepository->recupererRepertoireRacineGroupe($groupe->getId());

        $listGfr = $gfrRepository->recuperertoutFileGroupeParRepertoire($groupe->getId());

        return $this->render('groupe/repertoireGroupe.html.twig', [
            'groupe' => $groupe,
            'listGfr' => $listGfr,
            'repertoireRacine' => $repertoireRacine,
            'formRepertoire' => $form
        ]);
    }

    #[IsGranted(attribute: 'GROUPE_VIEW', subject: 'groupe')]
    #[Route('/groupe/{id}/upload_fichier', name: 'upload_file_groupe', methods:  ['GET', 'POST'])]
    public function uploadFileGroupe(
        Groupe $groupe,
        EntityManagerInterface $entityManager,
        Request $request,
        FlashMessageHelperInterface $flashMessageHelper,
        FileUploadService $fileUploadService,
    ) : Response {

        $utilisateur = $this->getUser();
        $repertoireRepository = $entityManager->getRepository(Repertoire::class);
        $gfrRepository = $entityManager->getRepository(GroupeFileRepertoire::class);

        $gfr =  new GroupeFileRepertoire();

        $formImport = $this->createForm(GroupeFileRepertoireType::class, $gfr, [
            'groupe' => $groupe,
        ]);

        $formImport->handleRequest($request);

        if ($formImport->isSubmitted() && $formImport->isValid()) {

            $uploadedFile = $formImport->get('file')->getData();
            $droit = $formImport->get('droit')->getData();
            $repertoire = $formImport->get('repertoire')->getData();
            $repertoireId = $repertoire->getId();

            if (!$uploadedFile) {
                $this->addFlash('error', 'Aucun fichier reçu.');
                return $this->redirectToRoute('upload_file_groupe', ['id' => $groupe->getId()]);
            }

            $nameFile = $uploadedFile->getClientOriginalName();

            $exists = $gfrRepository->existsFileGroupe($groupe->getId(), $nameFile, $repertoireId);
            if ($exists) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà pour votre groupe dans ce répertoire.',
                    $nameFile
                ));
                return $this->redirectToRoute('upload_file_groupe', ['id' => $groupe->getId()]);
            }

            try {
                $repertoire = $repertoireRepository->find($repertoireId);
                if (!$repertoire) {
                    throw new DomainException('Répertoire introuvable.');
                }

                $file = $fileUploadService->createFromUploadedFile(
                    $uploadedFile,
                    description: null,
                    validateYamlWhenYaml: true
                );

                $file->setUtilisateurFile($utilisateur);

                $gfr->setDroit($droit);
                $gfr->setGroupe($groupe);
                $gfr->setRepertoire($repertoire);
                $gfr->setFile($file);

                $entityManager->persist($file);
                $entityManager->persist($gfr);
                $entityManager->flush();

                $this->addFlash('success', sprintf(
                    'Fichier "%s" ajouté avec succès au groupe "%s" dans "%s".',
                    $nameFile,
                    $groupe->getNom(),
                    $repertoire->getFullPath()
                ));

                return $this->redirectToRoute('upload_file_groupe', ['id' => $groupe->getId()]);

            } catch (ParseException $e) {
                $this->addFlash('error', "YAML invalide : " . $e->getMessage());
                return $this->redirectToRoute('upload_file_groupe', ['id' => $groupe->getId()]);
            } catch (DomainException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('upload_file_groupe', ['id' => $groupe->getId()]);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Une erreur est survenue: ' . $e->getMessage());
                return $this->redirectToRoute('upload_file_groupe', ['id' => $groupe->getId()]);
            }
        }

        $flashMessageHelper->addFormErrorsAsFlash($formImport);

        return $this->render('groupe/uploadGroupe.html.twig', [
            'formImport' => $formImport->createView(),
            'groupe' => $groupe,
        ]);
    }


    #[IsGranted(attribute: 'GROUPE_VIEW', subject: 'groupe')]
    #[Route('/groupe/{id}/partager_fichier', name: 'partager_file_groupe', methods:  ['GET', 'POST'])]
    public function partagerFileGroupe(
        Groupe $groupe,
        Request $request,
        FlashMessageHelperInterface $flashMessageHelper,
        EntityManagerInterface $entityManager
    ) : Response
    {
        $utilisateur = $this->getUser();

        $filesUtilisateur = $entityManager
            ->getRepository(UtilisateurFileRepertoire::class)
            ->findFilesForUser($utilisateur);

        $fileChoices = [];

        $gfrRepository = $entityManager->getRepository(GroupeFileRepertoire::class);

        foreach ($filesUtilisateur as $ufr) {

            $file = $ufr->getFile();

            $existant = $gfrRepository->findOneBy(['file' => $file, 'groupe' => $groupe]);

            if (!$existant) {
                $repertoire = $ufr->getRepertoire();

                $displayName = sprintf(
                    "%s — %s",
                    $file->getNameFile(),
                    $repertoire->getFullPath()
                );

                $fileChoices[$displayName] = $file->getId();
            }
        }

        $formExistant = $this->createForm(PartagerFileGroupeType::class, null, [
            'file_choices' => $fileChoices,
            'groupe' => $groupe,
        ]);

        $formExistant->handleRequest($request);

        if ($formExistant->isSubmitted() && $formExistant->isValid()) {

            $data = $formExistant->getData();
            $fileId = $data['fileId'];
            $droit = $data['droit'];
            $repertoire = $formExistant->get('repertoire')->getData();
            $repertoireId = $repertoire->getId();

            $file = $entityManager->getRepository(File::class)->find($fileId);

            if (!$file) {
                $this->addFlash('error', "Fichier introuvable.");
                return $this->redirectToRoute('partager_file_groupe', ['id' => $groupe->getId()]);
            }

            $exists = $gfrRepository->existsFileGroupe($groupe->getId(), $file->getNameFile(), $repertoireId);

            if ($exists) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà pour votre groupe dans ce répertoire.',
                    $file->getNameFile()
                ));
                return $this->redirectToRoute('partager_file_groupe', ['id' => $groupe->getId()]);
            }

            $repertoireRepository = $entityManager->getRepository(Repertoire::class);
            $repertoire = $repertoireRepository->find($repertoireId);

            $gyr =  new GroupeFileRepertoire();
            $gyr->setFile($file);
            $gyr->setGroupe($groupe);
            $gyr->setDroit($droit);
            $gyr->setRepertoire($repertoire);

            $entityManager->persist($gyr);
            $entityManager->flush();

            $this->addFlash('success', 'Fichier partagé au groupe.');
            return $this->redirectToRoute('partager_file_groupe', ['id' => $groupe->getId()]);
        }

        $flashMessageHelper->addFormErrorsAsFlash($formExistant);

        return $this->render('groupe/partagerFileGroupe.html.twig', [
            'groupe' => $groupe,
            'formExistant' => $formExistant,
        ]);
    }

    #[Route('/groupe/{id}/supprimer_fichier/{fileId}', name: 'deleteFileGroupe', options: ["expose" => true], methods: ['DELETE'])]
    public function supprimerFileDuGroupe(
        Groupe $groupe,
        int $fileId,
        EntityManagerInterface $entityManager,
        Request $request,
    ): Response {

        $file = $entityManager->getRepository(File::class)->find($fileId);

        if (!$file) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $gfrRepository = $entityManager->getRepository(GroupeFileRepertoire::class);
        $ufrRepository = $entityManager->getRepository(UtilisateurFileRepertoire::class);

        $gfr = $gfrRepository->findByFileAndGroupe($file->getId(), $groupe->getId());

        if (!$gfr) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted('GROUPE_FILE_DELETE', $gfr);

        $submittedToken = $request->getPayload()->get('_token');

        if (!$this->isCsrfTokenValid('delete' . $groupe->getId() . $file->getId(), $submittedToken)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($gfr);
        $entityManager->flush();

        $stillUsedByUser = $ufrRepository->findOneBy(['file' => $file]) !== null;
        $stillUsedByAnyGroup = $gfrRepository->findOneBy(['file' => $file]) !== null;

        if (!$stillUsedByUser && !$stillUsedByAnyGroup) {

            if ($file instanceof BinaryFile) {
                $storageDir = (string) $this->getParameter('app.file_storage_dir');
                $path = rtrim($storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file->getStoragePath();

                if (is_file($path)) {
                    @unlink($path);
                }
            }

            $entityManager->remove($file);
            $entityManager->flush();
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }


    #[Route('/groupe/{id}/modifier-fichier/{textFileId}', name: 'modifier_text_file_groupe', methods: ['GET', 'POST'])]
    public function modifiertextFileGroupe(
        Groupe                 $groupe,
        int                    $textFileId,
        Request                $request,
        EntityManagerInterface $entityManager
    ): Response {

        $gfr = $entityManager->getRepository(GroupeFileRepertoire::class)->findByFileAndGroupe($textFileId, $groupe->getId());

        $this->denyAccessUnlessGranted('GROUPE_FILE_EDIT', $gfr);

        $textFile = $gfr->getFile();

        if (!$textFile instanceof TextFile) {
            $this->addFlash('error', 'Seuls les fichiers sous formats textes (un nom de fichier, et un contenu textuel) sont éditables.');
            return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
        }

        if ($request->isMethod('POST')) {
            $submittedToken = $request->request->get('_token');

            if ($this->isCsrfTokenValid('edit-text', $submittedToken)) {
                $fileContent = $request->request->get('text_content');

                try {
                    if ($textFile->isYaml()) {Yaml::parse($fileContent);}

                    $version = new TextFileVersion();
                    $version->setBodyFile($textFile->getBodyFile());
                    $version->setTextFileId($textFile);
                    $version->setDateEdition(new \DateTime());
                    $version->setUtilisateur($this->getUser());
                    $version->setCommentaire('Sauvegarde automatique avant modification du ' . (new \DateTime())->format('d/m/Y H:i:s'));

                    $entityManager->persist($version);

                    $textFile->setBodyFile($fileContent);
                    $entityManager->flush();

                    $this->addFlash('success', 'Fichier modifié avec succès.');
                    return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
                } catch (ParseException $e) {
                    $this->addFlash('error', 'Erreur dans le format YAML : ' . $e->getMessage());
                }
            } else {
                $this->addFlash('error', 'Token CSRF invalide.');
            }
        }

        return $this->render('groupe/editTextFileGroupe.html.twig', [
            'textFile' => $textFile,
            'groupe' => $groupe,
        ]);
    }

    #[IsGranted(attribute: 'GROUPE_EDIT', subject: 'groupe')]
    #[Route('/groupe/{id}/file/deplacer/{idFile}', name: 'file_deplacer_groupe', methods: ['GET', 'POST'])]
    public function deplacer_groupe(
        int                    $idFile,
        Groupe                 $groupe,
        Request                $request,
        EntityManagerInterface $entityManager
    ): Response {
        $gfrRepository = $entityManager->getRepository(GroupeFileRepertoire::class);

        $form = $this->createForm(DeplacerFileGroupeType::class, null, [
            'groupe' => $groupe,
        ]);
        $form->handleRequest($request);

        $file = $entityManager->getRepository(File::class)->find($idFile);

        if (!$file) {
            $this->addFlash('error', "Fichier inconnu.");
            return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $repertoire = $form->get('repertoire')->getData();

            $gfr = $gfrRepository->findByFileAndGroupe($idFile, $groupe->getId());

            if (!$gfr) {
                $this->addFlash('error', "Ce fichier n'est pas dans ce groupe.");
                return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
            }

            $nameFile = $file->getNameFile();
            $exists = $gfrRepository->existsFileGroupe($groupe->getId(), $nameFile, $repertoire->getId());
            if ($exists) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà pour votre groupe dans ce répertoire.',
                    $nameFile
                ));
                return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
            }

            $gfr->setRepertoire($repertoire);
            $entityManager->flush();

            $this->addFlash('success', "Fichier déplacé avec succès !");
            return $this->redirectToRoute('fichiers_groupe', ['id' => $groupe->getId()]);
        }

        return $this->render('file/deplacer.html.twig', [
            'form' => $form->createView(),
            'file' => $file,
        ]);
    }
}
