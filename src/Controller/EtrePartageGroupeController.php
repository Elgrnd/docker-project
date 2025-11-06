<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EtrePartageGroupeController extends AbstractController
{
    #[Route('/etre/partage/groupe', name: 'app_etre_partage_groupe')]
    public function index(): Response
    {
        return $this->render('etre_partage_groupe/listeGroupe.html.twig', [
            'controller_name' => 'EtrePartageGroupeController',
        ]);
    }
}
