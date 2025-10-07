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
        $containers = $dockerService->listContainers($isAdmin);

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
        return $this->render('docker/index.html.twig', [
            'containers' => $containers,
            'controller_name' => 'DockerController',
        ]);
    }
}
