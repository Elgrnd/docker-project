<?php

namespace App\Controller;

use App\Entity\Groupe;
use App\Entity\TextFile;
use App\Entity\VirtualMachine;
use App\Repository\GroupeRepository;
use App\Repository\VirtualMachineRepository;
use App\Service\DockerService;
use App\Service\ProxmoxService;
use App\Service\UtilisateurManagerInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use phpDocumentor\Reflection\Types\Collection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;


final class DockerController extends AbstractController
{

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[IsGranted('VM_MANAGE', 'virtualMachine')]
    #[Route('/virtual-machine/{id}/containers/', name: 'listContainers', options: ['expose' => true])]
    public function list(
        DockerService               $dockerService,
        VirtualMachine              $virtualMachine,
        ProxmoxService              $proxmoxService,
        UtilisateurManagerInterface $utilisateurManager,
        GroupeRepository $groupeRepository
    ): Response
    {
        if ($virtualMachine->getVmId() == null || $virtualMachine->getVmStatus() == "none") {
            $this->addFlash("error", "Vous n'avez pas encore créer de VM");
            return $this->redirectToRoute('index');
        }
        if ($virtualMachine->getVmStatus() !== "ready") {
            $this->addFlash("error", "Votre VM n'est pas encore prête !");
            return $this->redirectToRoute('index');
        }

        $user = $this->getUser();
        $containers = [];
        $vms = [];

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $users = $utilisateurManager->getUtilisateursAvecVm();
            foreach ($users as $userWithVm) {
                try {
                    $vmIp = $proxmoxService->verifVMIp($userWithVm->getVm());
                } catch (Exception $exception) {
                    $this->addFlash('error', $exception->getMessage() . "for user " . $userWithVm->getlogin());
                    return $this->redirectToRoute("index");
                }
                if ($vmIp) {
                    $groupes = $groupeRepository->findAll();
                    $accessibleVms = $this->getAccessibleVms($groupes);
                    $userContainers = $dockerService->listContainers($vmIp);
                    foreach ($userContainers as &$userContainer) {
                        $userContainer['user'] = $userWithVm->getLogin();
                        $userContainer['vmid'] = $userWithVm->getVm()->getId();
                    }
                    $containers = array_merge($containers, $userContainers);
                }
            }
        } else {
            try {
                $vmIp = $proxmoxService->verifVMIp($virtualMachine);

            } catch (Exception) {
                $this->addFlash('error', "le QGA n'est pas encore prêt pour la VM");
                return $this->redirectToRoute("index");
            }
            if ($vmIp) {
                $containers = $dockerService->listContainers($vmIp);
                foreach ($containers as &$container) {
                    $container['user'] = $user->getLogin();
                    $container['vmid'] = $virtualMachine->getId();
                }
                $groupes = $this->getUser()->getUtilisateurGroupe();
                $accessibleVms = $this->getAccessibleVms($groupes);
            }
        }

        // ---- MONITORING DES VM (TOUS LES UTILISATEURS) ----
        try {
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                // Admin : vue globale de toutes les VM
                $vms = $proxmoxService->getAdminVmOverview();
            } else {
                // Utilisateur normal : seulement sa VM
                $vmid = $virtualMachine->getVmId();
                if ($vmid) {
                    $runtime = $proxmoxService->getVMRuntimeStatus($vmid);

                    $vms = [[
                        'vmid' => $vmid,
                        'name' => 'VM ' . $user->getLogin(),
                        'status' => $runtime['status'] ?? 'unknown',
                        'cpu' => $runtime['cpu'] ?? null,
                        'maxcpu' => $runtime['maxcpu'] ?? null,
                        'mem' => $runtime['mem'] ?? null,
                        'maxmem' => $runtime['maxmem'] ?? null,
                        'disk' => $runtime['disk'] ?? null,
                        'maxdisk' => $runtime['maxdisk'] ?? null,
                        'uptime' => $runtime['uptime'] ?? null,
                    ]];
                } else {
                    $vms = [];
                }
            }
        } catch (Throwable $e) {
            $this->addFlash('error', 'Impossible de récupérer les informations de la VM : ' . $e->getMessage());
            $vms = [];
        }

        return $this->render('docker/listContainers.html.twig', [
            'containers' => $containers,
            'accessibleVms' => $accessibleVms,
            'vms' => $vms,
            'controller_name' => 'DockerController',
            'virtualMachine' => $virtualMachine,
        ]);
    }


    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[IsGranted('VM_MANAGE', 'virtualMachine')]
    #[Route('/virtual-machine/{virtualMachine}/container/{idContainer}/start', name: 'container_start')]
    public function start(string $idContainer, VirtualMachine $virtualMachine, DockerService $dockerService, ProxmoxService $proxmoxService): Response
    {
        $vmIp = $proxmoxService->verifVMIp($virtualMachine);
        $result = $dockerService->startContainer($idContainer, $vmIp);

        if ($result['success']) {
            $this->addFlash('success', 'Container started successfully.');
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('listContainers', ["id" => $virtualMachine->getId()]);
    }


    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[IsGranted('VM_MANAGE', 'virtualMachine')]
    #[Route('/virtual-machine/{virtualMachine}/container/{idContainer}/stop', name: 'container_stop')]
    public function stop(string $idContainer, VirtualMachine $virtualMachine, DockerService $dockerService, ProxmoxService $proxmoxService): Response
    {
        $vmIp = $proxmoxService->verifVMIp($virtualMachine);
        $result = $dockerService->stopContainer($idContainer, $vmIp);

        if ($result['success']) {
            $this->addFlash('success', 'Container stopped successfully.');
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('listContainers', ["id" => $virtualMachine->getId()]);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[IsGranted('VM_MANAGE', 'virtualMachine')]
    #[Route('/virtual-machine/{virtualMachine}/container/{idContainer}/remove', name: 'container_remove')]
    public function remove(string $idContainer, VirtualMachine $virtualMachine, DockerService $dockerService, ProxmoxService $proxmoxService): Response
    {
        $vmIp = $proxmoxService->verifVMIp($virtualMachine);
        $result = $dockerService->removeContainer($idContainer, $vmIp);

        if ($result['success']) {
            $this->addFlash('success', 'Container removed successfully.');
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('listContainers', ["id" => $virtualMachine->getId()]);
    }

    #[Route('/admin/vm/{vmid}/start', name: 'admin_vm_start')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminStartVm(string $vmid, ProxmoxService $proxmoxService): Response
    {
        try {
            $ok = $proxmoxService->startVM($vmid);
            if ($ok) {
                $this->addFlash('success', "VM $vmid démarrée avec succès.");
            } else {
                $this->addFlash('error', "Impossible de démarrer la VM $vmid.");
            }
        } catch (Throwable $e) {
            $this->addFlash('error', "Erreur lors du démarrage de la VM $vmid : " . $e->getMessage());
        }

        return $this->redirectToRoute('listContainers', ['id' => $this->getUser()->getVm()->getVmId()]);
    }

    #[Route('/admin/vm/{vmid}/stop', name: 'admin_vm_stop')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminStopVm(string $vmid, ProxmoxService $proxmoxService): Response
    {
        try {
            $ok = $proxmoxService->stopVM($vmid);
            if ($ok) {
                $this->addFlash('success', "VM $vmid arrêtée avec succès.");
            } else {
                $this->addFlash('error', "Impossible d'arrêter la VM $vmid.");
            }
        } catch (Throwable $e) {
            $this->addFlash('error', "Erreur lors de l'arrêt de la VM $vmid : " . $e->getMessage());
        }

        return $this->redirectToRoute('listContainers', ['id' => $this->getUser()->getVm()->getVmId()]);
    }

    #[Route('/admin/vm/{vmid}/delete', name: 'admin_vm_delete')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDeleteVm(string $vmid, ProxmoxService $proxmoxService): Response
    {
        try {
            $ok = $proxmoxService->deleteVM($vmid);
            if ($ok) {
                $this->addFlash('success', "VM $vmid supprimée avec succès.");
            } else {
                $this->addFlash('error', "Impossible de supprimer la VM $vmid.");
            }
        } catch (Throwable $e) {
            $this->addFlash('error', "Erreur lors de la suppression de la VM $vmid : " . $e->getMessage());
        }

        return $this->redirectToRoute('listContainers', ['id' => $this->getUser()->getVm()->getVmId()]);
    }

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface|Exception
     */
    #[Route('/yaml/deploy/{id}', name: 'deploy_yaml_file', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function deployYamlInVm(
        TextFile       $idFile,
        DockerService  $dockerService,
        ProxmoxService $proxmoxService
    ): Response
    {
        return $this->deployInVm($idFile, $proxmoxService, $dockerService, $this->getUser()->getVm());
    }

    /**
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface|Exception
     */
    #[Route('/groupe/{groupe}/deploy/{idFile}', name: 'deploy_yaml_file_groupe', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function deployYamlInVmGroup(
        TextFile       $idFile,
        Groupe         $groupe,
        DockerService  $dockerService,
        ProxmoxService $proxmoxService
    ): Response
    {
        if (!$groupe || !$groupe->getVm()) {
            $this->addFlash('error', "Le groupe ou la VM n'existe pas");
            $this->redirectToRoute("accueil");
        }

        if ($groupe->getVm()->getVmStatus() != 'ready') {
            $this->addFlash('error', "La VM n'est pas encore prête");
            $this->redirectToRoute("accueil");
        }
        return $this->deployInVm($idFile, $proxmoxService, $dockerService, $groupe->getVm());
    }


    #[IsGranted('ROLE_USER')]
    #[Route('/vm/status', name: 'vm_status', methods: ['GET'])]
    public function vmStatus(): JsonResponse
    {
        $user = $this->getUser();
        $status = null;
        if ($user->getVm()) {
            $status = $user->getVm()->getVmStatus();
        }

        return new JsonResponse([
            'status' => $status
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/vm/create', name: 'vm_create', methods: ['POST'])]
    public function createVm(EntityManagerInterface $entityManager, ProxmoxService $proxmoxService): JsonResponse
    {
        $user = $this->getUser();

        if ($user->getVm()->getVmId() !== null) {
            return new JsonResponse(['status' => 'already_exists'], 400);
        }
        $user->getVm()->setVmStatus('creating');
        $entityManager->flush();

        $proxmoxService->cloneUserVmAsynchrone($user->getLogin());

        return new JsonResponse(['status' => 'creating']);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/services', name: 'services_list', methods: ['GET'])]
    public function listServices(DockerService $dockerService, ProxmoxService $proxmoxService): Response
    {
        $user = $this->getUser();
        $vmip = $proxmoxService->verifVMIp($user->getVm()->getVmId());
        $services = $dockerService->listServices($vmip);
        return $this->render('docker/listServices.html.twig', [
            'services' => $services
        ]);
    }

    /**
     * @param TextFile $idFile
     * @param ProxmoxService $proxmoxService
     * @param DockerService $dockerService
     * @param VirtualMachine $vm
     * @return RedirectResponse
     * @throws Exception
     */
    public function deployInVm(TextFile $idFile, ProxmoxService $proxmoxService, DockerService $dockerService, VirtualMachine $vm): RedirectResponse
    {
        if (!$idFile->isYaml()) {
            throw new Exception("Le fichier n'est pas un fichier .yaml/.yml.");
        }
        try {
            $content = $idFile->getBodyFile();
            $baseName = $idFile->getNameFile() ?? 'compose.yaml';

            $projectName = preg_replace('/[^a-z0-9_]/', '_', strtolower(pathinfo($baseName, PATHINFO_FILENAME)));
            $remotePath = '/root/deploy/' . $projectName . '_l' . uniqid() . '.yaml';

            $vmIp = $proxmoxService->verifVMIp($vm);
            if (!$vmIp) {
                throw new Exception("Impossible de récupérer l'IP de la VM.");
            }

            $uploadError = $dockerService->sendContentToVm($content, $remotePath, $vmIp);
            if (str_contains($uploadError, 'Permanently added')) {
                $uploadError = '';
            }

            if (!empty($uploadError)) {
                throw new Exception("Erreur SCP vers la VM: " . $uploadError);
            }

            $cmd = sprintf('/usr/bin/docker compose -p %s -f %s up -d 2>&1', escapeshellarg($projectName), escapeshellarg($remotePath));
            $output = $dockerService->runInVm($cmd, $vmIp);

            $lines = explode("\n", $output);

            $important = array_filter($lines, fn($line) => str_contains(strtolower($line), 'error') ||
                str_contains(strtolower($line), 'warning')
            );

            if (!empty($important)) {
                throw new Exception("Erreur détectée pendant le déploiement :\n" . implode("\n", $important));
            }

            $fileExists = $dockerService->runInVm(
                "test -f " . escapeshellarg($remotePath) . " && echo 'OK' || echo 'KO'",
                $vmIp
            );
            if ($fileExists !== 'OK') {
                throw new Exception("Le fichier YAML n'a pas été transféré correctement.");
            }

            $containers = $dockerService->listContainers($vmIp);
            if (empty($containers)) {
                throw new Exception("Aucun conteneur n'a été créé après le déploiement.");
            }

            $this->addFlash('success', "Déploiement OK : " . $baseName);

        } catch (Throwable $e) {
            $this->addFlash('error', "ERREUR : " . $e->getMessage());
        }

        return $this->redirectToRoute('repertoire');
    }

    /**
     * A Refactor ! La fonction devrait surement se trouver dans Utilisateur
     * @param array $groupes
     * @return array[]
     */
    public function getAccessibleVms(array $groupes): array
    {
        $user = $this->getUser();
        $accessibleVms = [$user->getVM()->getVmId() => [
            'vm' => $user->getVM(),
            'label' => 'VM - User ' . $user->getLogin(),
        ]];

        foreach ($groupes as $groupe) {
            if ($groupe->getVm() && $groupe->getVm()->getVmStatus() == "ready") {
                $accessibleVms[$groupe->getVm()->getId()] = [
                    'vm' => $groupe->getVm(),
                    'label' => 'VM – Groupe ' . $groupe->getNom()
                ];
            }
        }
        return $accessibleVms;
    }


}