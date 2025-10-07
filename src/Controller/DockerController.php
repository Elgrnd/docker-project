<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DockerController extends AbstractController
{
    #[Route('/docker', name: 'app_docker')]
    public function index(): Response
    {
        return $this->render('docker/index.html.twig', [
            'controller_name' => 'DockerController',
        ]);
    }
}
