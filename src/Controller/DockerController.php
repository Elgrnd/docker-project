<?php

namespace App\Controller;

use App\Service\DockerService;
use App\Service\ProxmoxService;
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
    public function list(DockerService $dockerService, ProxmoxService $proxmoxService): Response
    {
        $containers = $dockerService->listContainers($proxmoxService->getVMIp($this->getUser()->getProxmoxVmid()));

        return $this->render('docker/listContainers.html.twig', [
            'containers' => $containers,
            'controller_name' => 'DockerController',
        ]);
    }
    #[Route('/container/{id}/start', name: 'container_start')]
    public function start(string $id, DockerService $dockerService): Response
    {
        $result = $dockerService->startContainer($id, $this->getUser()->getProxmoxVmid());

        if ($result['success']) {
            $this->addFlash('success', 'Container started successfully.');
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('listContainers');
    }

    #[Route('/container/{id}/stop', name: 'container_stop')]
    public function stop(string $id, DockerService $dockerService): Response
    {
        $result = $dockerService->stopContainer($id, $this->getUser()->getProxmoxVmid());

        if ($result['success']) {
            $this->addFlash('success', 'Container stopped successfully.');
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('listContainers');
    }

    #[Route('/container/{id}/remove', name: 'container_remove')]
    public function remove(string $id, DockerService $dockerService): Response
    {
        $result = $dockerService->removeContainer($id, $this->getUser()->getProxmoxVmid());

        if ($result['success']) {
            $this->addFlash('success', 'Container removed successfully.');
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('listContainers');
    }

    #[Route('/proxmox/start/{vmId}', name: 'container_remove')]
    public function starVM(string $vmId, ProxmoxService $proxmoxService): Response
    {
        if($proxmoxService->startVM($vmId)){
            return new Response("la vm a bien été lancé");
        } else {
            return new Response("problem");
        }
    }


}
