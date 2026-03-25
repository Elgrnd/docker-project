<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\Groupe;
use App\Entity\Repertoire;
use App\Entity\Utilisateur;
use App\Entity\UtilisateurFileRepertoire;
use App\Entity\VirtualMachine;
use App\Repository\FileRepository;
use App\Repository\RepertoireRepository;
use App\Service\DockerService;
use App\Service\ProxmoxService;
use App\Service\RepertoireService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use ZipArchive;


final class RepertoireController extends AbstractController
{
    #[IsGranted('REP_EDIT', subject: 'repertoire')]
    #[Route('/repertoire/rename/{id}', name: 'rename_repertoire', options: ["expose" => true], methods: ['POST'])]
    public function index(EntityManagerInterface $entityManager, Repertoire $repertoire, Request $request): Response
    {
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $nouveauNom = $data['name'] ?? null;



        $parent = $repertoire->getParent();
        if ($repertoire->getUtilisateurRepertoire() !== null){
            $existe = $entityManager->getRepository(Repertoire::class)->verifierNomDejaExistantUtilsateur($nouveauNom, $parent, $user->getId());
        } else {
            $existe = $entityManager->getRepository(Repertoire::class)->verifierNomDejaExistantGroupe($nouveauNom, $parent, $repertoire->getGroupeRepertoire());
        }

        if (empty($nouveauNom) || trim($nouveauNom) === '') {
            return $this->json([
                'success' => false,
                'message' => 'Le nom du répertoire ne peut pas être vide'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($existe != null){
            return $this->json([
                'success' => false,
                'message' => 'Un répertoire avec ce nom existe déjà à cet emplacement'
            ], Response::HTTP_CONFLICT);
        }

        $ancienNom = $repertoire->getName();
        $repertoire->setName(trim($nouveauNom));

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Répertoire renommé avec succès',
            'data' => [
                'id' => $repertoire->getId(),
                'oldName' => $ancienNom,
                'newName' => $repertoire->getName(),
                'fullPath' => $repertoire->getFullPath()
            ]
        ], Response::HTTP_OK);

    }

    #[IsGranted('ROLE_USER')]
    #[Route('/repertoire/corbeille', name: 'repertoire_corbeille')]
    #[Route('/groupe/{id}/repertoire/corbeille', name: 'repertoire_corbeille_groupe')]
    public function corbeille(
        RepertoireRepository $repertoireRepository,
        FileRepository       $fileRepository,
        ?Groupe              $groupe = null
    ): Response
    {
        if ($groupe) {
            $this->denyAccessUnlessGranted('GROUPE_EDIT', $groupe);

            $repertoires = $repertoireRepository->findDeletedByGroupe($groupe);
            $files   = $fileRepository->findDeletedByGroupe($groupe);

            return $this->render('repertoire/corbeilleRepertoireGroupe.html.twig', [
                'repertoires' => $repertoires,
                'files'   => $files,
                'groupe'      => $groupe
            ]);

        } else {
            $user = $this->getUser();

            $repertoires = $repertoireRepository->findDeletedByUser($user);
            $files   = $fileRepository->findDeletedByUser($user);

            return $this->render('repertoire/corbeilleRepertoire.html.twig', [
                'repertoires' => $repertoires,
                'files'   => $files,
            ]);
        }
    }

    #[IsGranted('REP_EDIT', subject: 'repertoire')]
    #[Route('/repertoire/supprimerCorbeille/{id}', name: 'deleteRepertoire', options: ["expose" => true], methods: ['DELETE'])]
    public function supprimerRepertoire(
        ?Repertoire $repertoire,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $submittedToken = $request->getPayload()->get('_token');
        if (!$this->isCsrfTokenValid('delete_repertoire' . $repertoire->getId(), $submittedToken)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $repertoire->softDelete();
        $em->persist($repertoire);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[IsGranted('GROUPE_EDIT', subject: 'groupe')]
    #[Route('/groupe/{id}/repertoire/supprimerCorbeille/{repertoireId}', name: 'deleteRepertoireGroupe', options: ["expose" => true], methods: ['DELETE'])]
    public function supprimerRepertoireGroupe(
        Groupe $groupe,
        RepertoireRepository $repertoireRepository,
        Request $request,
        EntityManagerInterface $em,
        int $repertoireId
    ): JsonResponse
    {
        $repertoire = $repertoireRepository->find($repertoireId);
        if (!$repertoire) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        $submittedToken = $request->getPayload()->get('_token');
        if (!$this->isCsrfTokenValid('delete_repertoire_groupe_' . $groupe->getId() . '_' . $repertoire->getId(), $submittedToken)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $repertoire->softDeleteForGroupe($groupe);

        $em->persist($repertoire);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[IsGranted('REP_EDIT', subject: 'repertoire')]
    #[Route('/repertoire/restaurer/{id}', name: 'restore_repertoire')]
    #[Route('/groupe/{idGroupe}/repertoire/restaurer/{id}', name: 'restore_repertoire_groupe')]
    public function restore(Repertoire $repertoire, EntityManagerInterface $em, ?int $idGroupe = null): Response
    {
        $repertoire->restore();
        $em->flush();

        $this->addFlash("success", "Le répertoire à bien été restauré");

        if ($idGroupe === null) {
            return $this->redirectToRoute('repertoire_corbeille');
        }

        $groupe = $em->getRepository(Groupe::class)->find($idGroupe);

        return  $this->redirectToRoute('repertoire_corbeille_groupe', ['id' => $groupe->getId()]);
    }


    #[IsGranted('REP_EDIT', subject: 'repertoire')]
    #[Route('/repertoire/supprimer/{id}', name: 'delete_repertoire_permanent')]
    public function supprime(Repertoire $repertoire, EntityManagerInterface $em, RepertoireService $repertoireService): Response
    {
        $repertoireService->deleteRepertoireWithFilesForUser($repertoire, $em);
        $em->flush();

        $this->addFlash("success", "Le répertoire à bien été supprimé");

        return $this->redirectToRoute('repertoire_corbeille');
    }

    #[IsGranted('REP_EDIT', subject: 'repertoire')]
    #[Route('/groupe/{idGroupe}/repertoire/supprimer/{id}', name: 'delete_repertoire_permanent_groupe')]
    public function supprimeRepGroupe(Repertoire $repertoire, EntityManagerInterface $em, RepertoireService $repertoireService, int $idGroupe): Response
    {
        $repertoireService->deleteRepertoireWithFilesForGroup($repertoire, $em);
        $em->flush();

        $this->addFlash("success", "Le répertoire à bien été supprimé");

        $groupe = $em->getRepository(Groupe::class)->find($idGroupe);

        return  $this->redirectToRoute('repertoire_corbeille_groupe', ['id' => $groupe->getId()]);
    }


    #[IsGranted('ROLE_USER')]
    #[Route('/repertoire/corbeille/delete-all', name: 'delete_repertoire_permanent_all')]
    public function deleteAll(
        EntityManagerInterface $em,
        RepertoireRepository $repo,
        RepertoireService $repertoireService,
    ): Response {
        $user = $this->getUser();

        $repertoires = $repo->findDeletedByUser($user);
        foreach ($repertoires as $rep) {
            $repertoireService->deleteRepertoireWithFilesForUser($rep, $em);
        }
        $em->flush();

        $this->addFlash('success', 'Tous vos répertoires supprimés définitivement !');
        return $this->redirectToRoute('repertoire_corbeille');
    }

    #[IsGranted('GROUPE_EDIT', subject: 'groupe')]
    #[Route('/groupe/{id}/repertoire/corbeille/delete-all', name: 'delete_repertoire_permanent_all_groupe')]
    public function deleteAllRepGroupe(
        EntityManagerInterface $em,
        RepertoireRepository $repo,
        RepertoireService $repertoireService,
        Groupe $groupe
    ): Response {
        $repertoires = $repo->findDeletedByGroupe($groupe);

        foreach ($repertoires as $rep) {
            $repertoireService->deleteRepertoireWithFilesForGroup($rep, $em);
        }

        $em->flush();

        $this->addFlash('success', 'Tous les répertoires du groupe supprimés définitivement !');

        return $this->redirectToRoute('repertoire_corbeille_groupe', ['id' => $groupe->getId()]);
    }



    /**
     * @throws Exception
     */
    #[IsGranted("REP_UPLOAD", subject: 'repertoire')]
    #[Route('/repertoire/telecharger/{id}', name: 'repertoire_telecharger_zip')]
    public function telechargerZip(
        Repertoire $repertoire,
        RepertoireService $repertoireService
    ): Response {
        $zipPath = sys_get_temp_dir() . '/repertoire_' . $repertoire->getId() . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->addFlash('error', "Impossible de créer l’archive ZIP.");
            $this->redirectToRoute("repertoire");
        }

        $repertoireService->ajouterRepertoireDansZip($repertoire, $zip, '');
        $zip->close();

        return $this->file($zipPath, $repertoire->getName() . '.zip')
            ->deleteFileAfterSend();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[IsGranted("ROLE_USER")]
    #[Route('/repertoire/copier-vm/{id}', name: 'repertoire_copier_vm')]
    public function copierRepertoireDansVm(
        Repertoire $repertoire,
        DockerService $dockerService,
        RepertoireService $repertoireService,
        ProxmoxService $proxmoxService
    ): Response
    {
        if (!$this->getUser() || !$this->getUser()->getVm()) {
            $this->addFlash('error', 'VM non disponible.');
            return $this->redirectToRoute('repertoire');
        }

        if ($this->getUser()->getVm()->getVmStatus() !== 'ready') {
            $this->addFlash('error', "La VM n'est pas encore prête");
            return $this->redirectToRoute('repertoire');
        }
        $this->copyRepertory($repertoire, $repertoireService, $dockerService, $proxmoxService, $this->getUser()->getVm());
        return $this->redirectToRoute("repertoire");
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[IsGranted("ROLE_ADMIN")]
    #[Route('/repertoire/{repertoire}/groupe/{groupe}/copier-vm', name: 'repertoire_copier_vm_groupe')]
    public function copierRepertoireGroupeDansVm(
        Repertoire $repertoire,
        Groupe $groupe,
        DockerService $dockerService,
        RepertoireService $repertoireService,
        ProxmoxService $proxmoxService
    ): Response
    {
        if (!$groupe || !$groupe->getVm()) {
            $this->addFlash('error', 'VM non disponible.');
            return $this->redirectToRoute('repertoire');
        }
        if ($groupe->getVm()->getVmStatus() !== 'ready') {
            $this->addFlash('error', 'VM non disponible.');
            return $this->redirectToRoute('repertoire');
        }
        $this->copyRepertory($repertoire, $repertoireService, $dockerService, $proxmoxService, $groupe->getVm());
        return $this->redirectToRoute("fichiers_groupe", ["id" => $groupe->getId()]);
    }

    /**
     * @param Repertoire $repertoire
     * @param RepertoireService $repertoireService
     * @param DockerService $dockerService
     * @param ProxmoxService $proxmoxService
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function copyRepertory(Repertoire $repertoire, RepertoireService $repertoireService, DockerService $dockerService, ProxmoxService $proxmoxService, VirtualMachine $virtualMachine): void
    {
        $zipPath = sys_get_temp_dir() . '/repertoire_' . $repertoire->getId() . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->addFlash('error', "Impossible de créer l’archive ZIP.");
            return;
        }

        $repertoireService->ajouterRepertoireDansZip($repertoire, $zip, '');
        $zip->close();

        try {
            $dockerService->deployZipInVm(
                $zipPath,
                '/root/' . str_replace(' ', '_', $repertoire->getName()),
                $proxmoxService->verifVMIp($virtualMachine)
            );

            $this->addFlash('success', 'Répertoire copié dans la VM avec succès.');
        } catch (Exception) {
            $this->addFlash('error', 'Erreur lors de la copie dans la VM.');
        }

        @unlink($zipPath);
    }

    #[IsGranted("ROLE_USER")]
    #[Route('/vm/copier', name: 'copier_vm', methods: ['POST'])]
    public function copierVm(
        Request $request,
        DockerService $docker,
        ProxmoxService $proxmox,
        EntityManagerInterface $em,
        KernelInterface $kernel,
        RepertoireRepository $repertoireRepository,
        RepertoireService $repertoireService
    ): Response {
        $user = $this->getUser();

        $vm = $user->getVm();
        if (!$vm || $vm->getVmStatus() !== 'ready') {
            $this->addFlash('error', 'VM manquante ou pas encore prête');
            return $this->redirectToRoute('repertoire');
        }

        try {
            $vmIp = $proxmox->verifVMIp($vm);
            if (!$vmIp) throw new RuntimeException("IP VM introuvable");

            $projectDir = $kernel->getProjectDir();
            $extractDir = $projectDir . '/var/tmp_vm_extract/' . $user->getId();

            if (is_dir($extractDir)) {
                $this->rrmdir($extractDir);
            }

            $docker->pullDirFromVmAsTarAndExtract('/root', $extractDir, $vmIp);

            $racine = $repertoireRepository->recupererRepertoireRacineUtilisateur($user->getId());
            if (!$racine) throw new \RuntimeException("Répertoire racine introuvable");

            $copieVm = $repertoireRepository->findOneBy([
                'parent' => $racine,
                'name' => 'copieVM',
                'utilisateur_repertoire' => $user,
            ]);

            if (!$copieVm) {
                $copieVm = new Repertoire();
                $copieVm->setName('copieVM');
                $copieVm->setParent($racine);
                $copieVm->setUtilisateurRepertoire($user);
                $em->persist($copieVm);
                $em->flush();
            } else {
                $repertoireService->clearRepertoire($copieVm, $em);
            }

            $res = $repertoireService->importLocalDirIntoRepertoire(
                $extractDir,
                $copieVm,
                $user,
                $em
            );

            $this->addFlash('success', "copieVM créé : {$res['dirs']} dossier(s), {$res['files']} fichier(s) texte importé(s).");
        } catch (\Throwable $e) {
            $this->addFlash('error', "Copie VM échouée: " . $e->getMessage());
        }

        return $this->redirectToRoute('repertoire');
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }

}
