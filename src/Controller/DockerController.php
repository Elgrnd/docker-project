<?php

namespace App\Controller;

use App\Service\DockerService;
use App\Service\ProxmoxService;
use App\Service\UtilisateurManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class DockerController extends AbstractController
{
    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[Route('/containers', name: 'listContainers')]
    public function list(DockerService $dockerService, ProxmoxService $proxmoxService, UtilisateurManagerInterface $utilisateurManager): Response
    {
        $user = $this->getUser();
        $containers = [];
        if(in_array('ROLE_ADMIN', $user->getRoles())) {
            $users = $utilisateurManager->getUtilisateursAvecVm();

            foreach ($users as $user) {
                $vmIp = $proxmoxService->getVMIp($user->getgetProxmoxVmid());
                if($vmIp) {
                    $userContainers = $dockerService->listContainers($vmIp);
                    foreach ($userContainers as $userContainer) {
                        $userContainer['user'] = $user->getLogin();
                        $userContainer['vmid'] = $user->getProxmoxVmid();
                    }
                    $containers = array_merge($containers, $userContainers);
                }
            }
        } else {
            if ($user->getProxmoxVmid()) {
                $vmIp = $proxmoxService->getVMIp($user->getProxmoxVmid());
                if ($vmIp) {
                    $containers = $dockerService->listContainers($vmIp);
                    foreach ($containers as &$container) {
                        $container['user'] = $user->getLogin();
                        $container['vmid'] = $user->getProxmoxVmid();
                    }

                }
            }
        }

        return $this->render('docker/listContainers.html.twig', [
            'containers' => $containers,
            'controller_name' => 'DockerController',
        ]);
    }

    #[Route('/container/{id}/start', name: 'container_start')]
    public function start(string $id, DockerService $dockerService, ProxmoxService $proxmoxService): Response
    {
        $result = $dockerService->startContainer($id, $proxmoxService->getVMIp($this->getUser()->getProxmoxVmid()));

        if ($result['success']) {
            $this->addFlash('success', 'Container started successfully.');
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute("listContainers");
    }

    #[Route('/container/{id}/stop', name: 'container_stop')]
    public function stop(string $id, DockerService $dockerService, ProxmoxService $proxmoxService): Response
    {
        $result = $dockerService->stopContainer($id, $proxmoxService->getVMIp($this->getUser()->getProxmoxVmid()));

        if ($result['success']) {
            $this->addFlash('success', 'Container stopped successfully.');
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('listContainers');
    }

    #[Route('/container/{id}/remove', name: 'container_remove')]
    public function remove(string $id, DockerService $dockerService, ProxmoxService $proxmoxService): Response
    {
        $result = $dockerService->removeContainer($id, $proxmoxService->getVMIp($this->getUser()->getProxmoxVmid()));

        if ($result['success']) {
            $this->addFlash('success', 'Container removed successfully.');
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('listContainers');
    }



}
