<?php

namespace App\Controller;

use App\Service\DockerService;
use phpDocumentor\Reflection\Types\This;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DockerController extends AbstractController
{
    #[Route('/containers', name: 'listContainers')]
    public function list(DockerService $dockerService): Response
    {
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $containers = $dockerService->listContainers();

        if(!$isAdmin) {
            $newContainer = [];
            foreach ($containers as $container)
            {
                if(str_contains($container['name'], 'user_' . $this->getUser()->getUserIdentifier())) {
                    $newContainer[] = $container;
                }
            }
            $containers = $newContainer;
        }

        return $this->render('docker/listContainers.html.twig', [
            'containers' => $containers,
            'controller_name' => 'DockerController',
        ]);
    }
    #[Route('/container/{id}/start', name: 'container_start')]
    public function start(string $id, DockerService $dockerService): Response
    {
        $result = $dockerService->startContainer($id);

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
        $result = $dockerService->stopContainer($id);

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
        $result = $dockerService->removeContainer($id);

        if ($result['success']) {
            $this->addFlash('success', 'Container removed successfully.');
        } else {
            $this->addFlash('error', $result['message']);
        }

        return $this->redirectToRoute('listContainers');
    }



}
