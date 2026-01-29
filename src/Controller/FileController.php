<?php

namespace App\Controller;

use App\Entity\BinaryFile;
use App\Entity\File;
use App\Entity\GroupeFileRepertoire;
use App\Entity\Repertoire;
use App\Entity\UtilisateurFileRepertoire;
use App\Form\DeplacerFileType;
use App\Form\DirectoryType;
use App\Form\UploadFileType;
use App\Service\FileUploadService;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Exception\ParseException;

final class FileController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/upload', name: 'file_upload', methods: ['GET', 'POST'])]
    public function upload(
        Request $request,
        EntityManagerInterface $entityManager,
        FlashMessageHelperInterface $flashMessageHelperInterface,
        FileUploadService $fileUploadService,
    ): Response {
        $utilisateur = $this->getUser();
        $repertoireRepository = $entityManager->getRepository(Repertoire::class);

        $form = $this->createForm(UploadFileType::class, null, [
            'method' => 'POST',
            'action' => $this->generateUrl('file_upload'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $repertoireId = $form->get('repertoire')->getData();
            $uploadedFile = $form->get('file')->getData();
            $description = $form->get('description')->getData();

            if (!$uploadedFile) {
                $this->addFlash('error', 'Aucun fichier reçu.');
                return $this->redirectToRoute('file_upload');
            }

            $nameFile = $uploadedFile->getClientOriginalName();

            $exists = $entityManager
                ->getRepository(UtilisateurFileRepertoire::class)
                ->existsFileUtilisateur($utilisateur->getId(), $nameFile, $repertoireId);

            if ($exists) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà pour votre compte dans ce répertoire.',
                    $nameFile
                ));
                return $this->redirectToRoute('file_upload');
            }

            try {
                $repertoire = $repertoireRepository->find($repertoireId);
                if (!$repertoire) {
                    throw new DomainException('Répertoire introuvable.');
                }

                $file = $fileUploadService->createFromUploadedFile(
                    $uploadedFile,
                    description: $description,
                    validateYamlWhenYaml: true
                );

                $file->setUtilisateurFile($utilisateur);

                $ufr = new UtilisateurFileRepertoire();
                $ufr->setUtilisateur($utilisateur);
                $ufr->setRepertoire($repertoire);
                $ufr->setFile($file);

                $entityManager->persist($file);
                $entityManager->persist($ufr);
                $entityManager->flush();

                $this->addFlash('success', sprintf(
                    'Fichier "%s" importé avec succès dans "%s".',
                    $nameFile,
                    $repertoire->getFullPath()
                ));

                return $this->redirectToRoute('file_upload');

            } catch (ParseException $e) {
                $this->addFlash('error', "YAML invalide : " . $e->getMessage());
                return $this->redirectToRoute('file_upload');
            } catch (DomainException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('file_upload');
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Une erreur est survenue: ' . $e->getMessage());
                return $this->redirectToRoute('file_upload');
            }
        }

        $flashMessageHelperInterface->addFormErrorsAsFlash($form);

        return $this->render('file/upload.html.twig', [
            'formulaireFile' => $form->createView(),
        ]);
    }

    #[IsGranted('FILE_OWNER', subject: 'file')]
    #[Route('/file/supprimer/{id}', name: 'deleteFile', options: ["expose" => true], methods: ['DELETE'])]
    public function supprimerFile(
        ?File $file,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$file) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $submittedToken = $request->getPayload()->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $file->getId(), $submittedToken)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $ufrRepo = $entityManager->getRepository(UtilisateurFileRepertoire::class);
        $gfrRepo = $entityManager->getRepository(GroupeFileRepertoire::class);

        $ufrRepo->supprimerFileUtilisateurParRepertoire($file->getId());
        $entityManager->flush();

        $stillHasUfr = $ufrRepo->findOneBy(['file' => $file]) !== null;
        $stillHasGfr = $gfrRepo->findOneBy(['file' => $file]) !== null;

        if (!$stillHasUfr && !$stillHasGfr) {
            if ($file instanceof BinaryFile) {
                $storageDir = (string) $this->getParameter('app.file_storage_dir');
                $path = rtrim($storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim((string) $file->getStoragePath(), DIRECTORY_SEPARATOR);

                if (is_file($path)) {
                    if (!unlink($path)) {
                        throw new \RuntimeException('Impossible de supprimer le fichier sur disque : ' . $path);
                    }
                }
            }
            $entityManager->remove($file);
            $entityManager->flush();
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[IsGranted("FILE_OWNER", subject: 'file')]
    #[Route('/file/deplacer/{id}', name: 'file_deplacer', methods: ['GET', 'POST'])]
    public function deplacer(
        File $file,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $utilisateur = $this->getUser();

        $form = $this->createForm(DeplacerFileType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $repertoire = $form->get('repertoire')->getData();

            $ufr = $entityManager->getRepository(UtilisateurFileRepertoire::class)
                ->findOneBy(['file' => $file, 'utilisateur' => $utilisateur]);

            if (!$ufr) {
                $this->addFlash('error', "Accès au fichier introuvable dans vos répertoires.");
                return $this->redirectToRoute('repertoire');
            }

            $exists = $entityManager
                ->getRepository(UtilisateurFileRepertoire::class)
                ->existsFileUtilisateur($utilisateur->getId(), $file->getNameFile(), $repertoire->getId());

            if ($exists) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà pour votre compte dans ce répertoire.',
                    $file->getNameFile()
                ));
                return $this->redirectToRoute('repertoire');
            }

            $ufr->setRepertoire($repertoire);
            $entityManager->flush();

            $this->addFlash('success', "Fichier déplacé avec succès !");
            return $this->redirectToRoute('repertoire');
        }

        return $this->render('file/deplacer.html.twig', [
            'form' => $form->createView(),
            'file' => $file,
        ]);
    }

    #[IsGranted("FILE_DOWNLOAD", subject: 'file')]
    #[Route('/file/{id}/download', name: 'file_download', options: ["expose" => true], methods: ['GET'])]
    public function download(File $file): Response
    {
        if ($file instanceof \App\Entity\TextFile) {
            $content = $file->getBodyFile() ?? '';
            $response = new Response($content);

            $response->headers->set('Content-Type', $file->getMimeType() ?: 'text/plain; charset=utf-8');
            $response->headers->set('X-Content-Type-Options', 'nosniff');

            $response->headers->set(
                'Content-Disposition',
                $response->headers->makeDisposition(
                    ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                    $file->getNameFile()
                )
            );

            return $response;
        }

        if ($file instanceof BinaryFile) {
            $storageDir = (string) $this->getParameter('app.file_storage_dir');
            $path = rtrim($storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim((string) $file->getStoragePath(), DIRECTORY_SEPARATOR);

            if (!is_file($path)) {
                throw $this->createNotFoundException('Fichier introuvable sur le disque.');
            }

            $response = new BinaryFileResponse($path);
            $response->headers->set('Content-Type', $file->getMimeType() ?: 'application/octet-stream');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getNameFile());

            return $response;
        }

        throw $this->createNotFoundException('Type de fichier non supporté.');
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
        $uyrRepository = $entityManager->getRepository(UtilisateurFileRepertoire::class);

        if ($form->isSubmitted() && $form->isValid()) {
            $repertoire->setUtilisateurRepertoire($utilisateur);

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

        $repertoireRacine = $repertoireRepository->recupererRepertoireRacineUtilisateur($utilisateur->getId());

        $listUyr = $uyrRepository->recuperertoutFileUtilisateurParRepertoire($utilisateur->getId());

        return $this->render('repertoire/repertoirePerso.html.twig', [
            'listUyr' => $listUyr,
            'formRepertoire' => $form,
            'repertoireRacine' => $repertoireRacine,
        ]);
    }
}
