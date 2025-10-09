<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EtrePartageController extends AbstractController
{
    #[Route('/etre/partage', name: 'app_etre_partage')]
    public function index(): Response
    {
        return $this->render('etre_partage/index.html.twig', [
            'controller_name' => 'EtrePartageController',
        ]);
    }
}
