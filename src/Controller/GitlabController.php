<?php

namespace App\Controller;

use App\Entity\UtilisateurYamlFileRepertoire;
use App\Entity\YamlFile;
use App\Form\AjouterBiblioRepertoireType;
use App\Form\GitlabUrlType;
use App\Service\GitlabApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

class GitlabController extends AbstractController
{
    #[IsGranted("ROLE_USER")]
    #[Route('/gitlab/url', name: 'gitlab_url')]
    public function gitlabUrl(
        Request $request,
        EntityManagerInterface $em,
        GitlabApiService $gitlab
    ): Response {

        $utilisateur = $this->getUser();

        $form = $this->createForm(GitlabUrlType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $url = $utilisateur->getGitlabUrl();

            if ($url !== null && !$gitlab->isValidGitlabUrl($url)) {
                $this->addFlash('error', "URL GitLab invalide.");
                return $this->redirectToRoute('gitlab_url');
            }
            else if ($url !== null && !$gitlab->isReachableGitlabProjectUrl($url)) {
                $this->addFlash('error', "Aucun dépôt Gitlab connu à cette adresse.");
                return $this->redirectToRoute('gitlab_url');
            }

            $em->persist($utilisateur);
            $em->flush();

            $this->addFlash('success', 'URL GitLab enregistrée !');

            return $this->redirectToRoute('gitlab_fichiers');
        }

        return $this->render('gitlab/url.html.twig', [
            'form' => $form,
        ]);
    }



    #[IsGranted("ROLE_USER")]
    #[Route('/gitlab/fichiers', name: 'gitlab_fichiers')]
    public function liste(GitlabApiService $gitlab): Response
    {
        $utilisateur = $this->getUser();

        if ($utilisateur->getGitlabUrl() === null) {
            return $this->redirectToRoute('gitlab_url');
        }

        $url = $utilisateur->getGitlabUrl();

        $parsed = $gitlab->parseGitlabUrl($url);

        if (!$parsed) {
            $this->addFlash('error', "URL GitLab invalide.");
            return $this->redirectToRoute('gitlab_url');
        }

        $host = $parsed['host'];
        $projectId = $parsed['projectId'];
        $branch = $parsed['branch'];
        $namespace = $parsed['namespace'];
        $project = $parsed['project'];

        $files = [];
        $page = 1;

        do {
            $url = "https://$host/api/v4/projects/$projectId/repository/tree?recursive=true&ref=$branch&per_page=100&page=$page";

            $result = $gitlab->request($url);

            if (!is_array($result) || count($result) === 0) break;

            $files = array_merge($files, $result);
            $page++;

        } while (true);

        if ($files === null) {
            return new Response("Erreur lors de la récupération depuis GitLab", 500);
        }

        // Ne garder que les fichiers YAML
        $files = array_filter($files, function ($item) use ($gitlab, $host, $projectId, $branch) {
            if (!isset($item['path'])) return false;
            if ($item['type'] !== 'blob') return false;

            $filename = basename($item['path']);

            // Vérifier nom et extension
            if (!preg_match('/^[^.].*\.ya?ml$/i', $filename)) return false;

            // Vérifier que le fichier n’est pas vide via API RAW
            $rawUrl = "https://$host/api/v4/projects/$projectId/repository/files/"
                . rawurlencode($item['path']) . "/raw?ref=$branch";

            try {
                $content = $gitlab->request($rawUrl);
            } catch (\Exception $e) {
                return false;
            }

            if (trim($content) === '') return false;

            try {
                Yaml::parse($content);
            } catch (ParseException $e) {
                return false;
            }

            return true;
        });

        $tree = $gitlab->buildTree($files);

        return $this->render("gitlab/arborescence.html.twig", [
            "tree" => $tree,
            "namespace" => $namespace,
            "project" => $project,
            "branch" => $branch,
            "host" => $host
        ]);
    }

    #[IsGranted("ROLE_USER")]
    #[Route('/gitlab/fichier/{path}', name: 'gitlab_fichier', requirements: ['path' => '.+'])]
    public function fichier(string $path, GitlabApiService $gitlab): Response
    {
        $utilisateur = $this->getUser();
        $url = $utilisateur->getGitlabUrl();

        $parsed = $gitlab->parseGitlabUrl($url);

        if (!$parsed) {
            $this->addFlash('error', "URL GitLab invalide.");
            return $this->redirectToRoute('gitlab_url');
        }

        $host = $parsed['host'];
        $projectId = $parsed['projectId'];
        $branch = $parsed['branch'];

        // Encodage complet du path GitLab
        $encodedPath = rawurlencode($path);

        // URL GitLab RAW file API
        $rawUrl = "https://$host/api/v4/projects/$projectId/repository/files/$encodedPath/raw?ref=$branch";

        // Récupération du contenu avec ton service cURL
        try {
            $content = $gitlab->request($rawUrl);
        } catch (\Exception $e) {
            return new Response("Erreur GitLab : " . $e->getMessage(), 500);
        }

        if (!is_string($content)) {
            return new Response("Impossible de récupérer le fichier.", 500);
        }

        return new Response($content); // string valide
    }

    #[IsGranted("ROLE_USER")]
    #[\Symfony\Component\Routing\Attribute\Route("/gitlab/ajouter_gitlab_file", name: 'ajouterAuRepertoireGitlab', methods: ['GET', 'POST'])]
    public function ajouterAuRepertoire(Request $request, EntityManagerInterface $entityManager, GitlabApiService $gitlab): Response
    {
        $pathFile = $request->query->get('path');
        $nameFile = basename($pathFile);

        if (!$pathFile) {
            $this->addFlash('error', 'Fichier GitLab non précisé.');
            return $this->redirectToRoute('gitlab_fichiers');
        }

        // Récupération directe via service
        $utilisateur = $this->getUser();
        $parsed = $gitlab->parseGitlabUrl($utilisateur->getGitlabUrl());
        $host = $parsed['host'];
        $projectId = $parsed['projectId'];
        $branch = $parsed['branch'];

        $rawUrl = "https://$host/api/v4/projects/$projectId/repository/files/".rawurlencode($pathFile)."/raw?ref=$branch";
        $bodyFile = $gitlab->request($rawUrl);

        $yamlFile = new YamlFile();
        $yamlFile->setNameFile($nameFile);

        $form = $this->createForm(AjouterBiblioRepertoireType::class, null, [
            'method' => 'POST',
            'action' => $this->generateUrl('ajouterAuRepertoireGitlab', ['path' => $pathFile]),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $repertoire = $data['repertoire'];

            $utilisateur = $this->getUser();

            $yamlFile->setBodyFile($bodyFile);
            $yamlFile->setUtilisateurYamlfile($utilisateur);

            $uyr = new UtilisateurYamlFileRepertoire();
            $uyr->setYamlFile($yamlFile);
            $uyr->setRepertoire($repertoire);
            $uyr->setUtilisateur($utilisateur);


            $repo = $entityManager->getRepository(UtilisateurYamlFileRepertoire::class);

            if ($repo->existsYamlFileUtilisateur($utilisateur->getId(), $yamlFile->getNameFile(), $repertoire)) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà dans ce répertoire.',
                    $yamlFile->getNameFile()
                ));
                return $this->redirectToRoute('gitlab_fichiers');
            }

            $entityManager->persist($yamlFile);
            $entityManager->persist($uyr);
            $entityManager->flush();

            $this->addFlash('success', 'Fichier ajouté à votre répertoire avec succès');

            return $this->redirectToRoute('gitlab_fichiers');
        }

        $routeAnnuler = 'gitlab_fichiers';

        return $this->render('yaml_file/ajouterAuRepertoire.html.twig', [
            'formulaire' => $form->createView(),
            'yamlFileBiblio' => $yamlFile,
            'routeAnnuler' => $routeAnnuler,
        ]);
    }

    #[IsGranted("ROLE_USER")]
    #[Route('/gitlab/supprimer-url', name: 'gitlab_supprimer_url')]
    public function supprimerUrl(EntityManagerInterface $em): Response
    {
        $utilisateur = $this->getUser();

        if ($utilisateur === null) {
            return $this->redirectToRoute('gitlab_url');
        }

        // Effacer l’URL stockée
        $utilisateur->setGitlabUrl(null);

        $em->persist($utilisateur);
        $em->flush();

        $this->addFlash('success', 'URL GitLab supprimée.');

        return $this->redirectToRoute('gitlab_url');
    }

}
