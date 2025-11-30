<?php

namespace App\Controller;

use App\Service\DockerService;
use App\Service\ProxmoxService;
use App\Service\UtilisateurManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;


final class DockerController extends AbstractController
{

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/containers', name: 'listContainers')]
    public function list(
        DockerService               $dockerService,
        ProxmoxService              $proxmoxService,
        UtilisateurManagerInterface $utilisateurManager
    ): Response
    {
        if ($this->getUser()->getVmStatus() !== "ready") {
            $this->addFlash("error", "Votre VM n'est pas encore prête !");
            return $this->redirectToRoute('index');
        }

        $user = $this->getUser();
        $containers = [];
        $vms = []; // 👈 nouveau tableau pour le monitoring admin

        // ---- CONTENEURS (inchangé) ----
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $users = $utilisateurManager->getUtilisateursAvecVm();
            foreach ($users as $userWithVm) {
                try {
                    $vmIp = $proxmoxService->getVMIp($userWithVm->getProxmoxVmid());
                } catch (\Exception $e) {
                    $this->addFlash('error', "le QGA n'est pas encore prêt pour la VM");
                    return $this->redirectToRoute("index");
                }
                if ($vmIp) {
                    $userContainers = $dockerService->listContainers($vmIp);
                    foreach ($userContainers as &$userContainer) {
                        $userContainer['user'] = $userWithVm->getLogin();
                        $userContainer['vmid'] = $userWithVm->getProxmoxVmid();
                    }
                    $containers = array_merge($containers, $userContainers);
                }
            }
        } else {
            if ($user->getProxmoxVmid()) {
                try {
                    $vmIp = $proxmoxService->getVMIp($user->getProxmoxVmid());
                } catch (\Exception $e) {
                    $this->addFlash('error', "le QGA n'est pas encore prêt pour la VM");
                    return $this->redirectToRoute("index");
                }
                if ($vmIp) {
                    $containers = $dockerService->listContainers($vmIp);
                    foreach ($containers as &$container) {
                        $container['user'] = $user->getLogin();
                        $container['vmid'] = $user->getProxmoxVmid();
                    }
                }
            }
        }

        // ---- VM MONITORING ADMIN ----
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            try {
                $vms = $proxmoxService->getAdminVmOverview();
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Impossible de récupérer les informations des VM : ' . $e->getMessage());
                $vms = [];
            }
        }

        return $this->render('docker/listContainers.html.twig', [
            'containers' => $containers,
            'vms' => $vms,               // 👈 on envoie à Twig
            'controller_name' => 'DockerController',
        ]);
    }


    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/container/{vmid}/{id}/start', name: 'container_start')]
    public function start(string $id, string $vmid, DockerService $dockerService, ProxmoxService $proxmoxService): Response
    {
        $vmIp = $proxmoxService->getVMIp($vmid);
        $result = $dockerService->startContainer($id, $vmIp);

        if ($result['success']) {
            $this->addFlash('success', 'Container started successfully.');
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute("listContainers");
    }


    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/container/{vmid}/{id}/stop', name: 'container_stop')]
    public function stop(string $id, string $vmid, DockerService $dockerService, ProxmoxService $proxmoxService): Response
    {
        $vmIp = $proxmoxService->getVMIp($vmid);
        $result = $dockerService->stopContainer($id, $vmIp);

        if ($result['success']) {
            $this->addFlash('success', 'Container stopped successfully.');
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('listContainers');
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/container/{vmid}/{id}/remove', name: 'container_remove')]
    public function remove(string $id, string $vmid, DockerService $dockerService, ProxmoxService $proxmoxService): Response
    {
        $vmIp = $proxmoxService->getVMIp($vmid);
        $result = $dockerService->removeContainer($id, $vmIp);

        if ($result['success']) {
            $this->addFlash('success', 'Container removed successfully.');
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('listContainers');
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
        } catch (\Throwable $e) {
            $this->addFlash('error', "Erreur lors du démarrage de la VM $vmid : " . $e->getMessage());
        }

        return $this->redirectToRoute('listContainers');
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
        } catch (\Throwable $e) {
            $this->addFlash('error', "Erreur lors de l'arrêt de la VM $vmid : " . $e->getMessage());
        }

        return $this->redirectToRoute('listContainers');
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
        } catch (\Throwable $e) {
            $this->addFlash('error', "Erreur lors de la suppression de la VM $vmid : " . $e->getMessage());
        }

        return $this->redirectToRoute('listContainers');
    }


}
