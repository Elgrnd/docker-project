<?php

namespace App\Controller;

use App\Repository\YamlFileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class YamlFileController extends AbstractController
{
    #[Route('/repertoire', name: 'repertoire', methods: ['GET'])]
    public function afficherRepertoire(YamlFileRepository $yamlFileRepository): Response {

        $yamlFiles = $yamlFileRepository->findByLogin($this->getUser()->getUserIdentifier());
        return $this->render('yaml_file/repertoirePerso.html.twig', ['yamlFiles' => $yamlFiles]);

    }
}
