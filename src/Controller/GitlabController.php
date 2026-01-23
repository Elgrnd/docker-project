<?php

namespace App\Controller;

use App\Entity\UtilisateurYamlFileRepertoire;
use App\Entity\YamlFile;
use App\Form\AjouterBiblioRepertoireType;
use App\Form\GitlabUrlType;
use App\Service\GitlabApiException;
use App\Service\GitlabApiService;
use App\Service\GitlabTokenCryptoService;
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
        GitlabApiService $gitlab,
        GitlabTokenCryptoService $crypto
    ): Response {

        $utilisateur = $this->getUser();

        $form = $this->createForm(GitlabUrlType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $url = $utilisateur->getGitlabUrl();
            $plainToken = $form->get('gitlabToken')->getData();

            if ($url === null || !$gitlab->isValidGitlabUrl($url)) {
                $this->addFlash('error', "URL GitLab invalide.");
                return $this->redirectToRoute('gitlab_url');
            }
            if ($plainToken) {
                try {
                    $gitlab->assertProjectReachable($url, $plainToken);
                } catch (GitlabApiException $e) {
                    $code = $e->getStatusCode();

                    if ($code === 401) {
                        $this->addFlash('error', "❌ Token GitLab invalide ou expiré.");
                        return $this->redirectToRoute('gitlab_url');
                    }
                    if ($code === 403) {
                        $this->addFlash('error', "⛔ Token valide mais accès refusé au dépôt (droits insuffisants).");
                        return $this->redirectToRoute('gitlab_url');
                    }
                    if ($code === 404) {
                        $this->addFlash('error', "❌ Projet introuvable : vérifiez l’URL GitLab.");
                        return $this->redirectToRoute('gitlab_url');
                    }
                    if ($code >= 500) {
                        $this->addFlash('error', "⚠️ GitLab est indisponible pour le moment (HTTP $code).");
                        return $this->redirectToRoute('gitlab_url');
                    }

                    $this->addFlash('error', "⚠️ Erreur GitLab (HTTP $code).");
                    return $this->redirectToRoute('gitlab_url');

                } catch (\RuntimeException $e) {
                    // Erreur réseau cURL (code 0)
                    $this->addFlash('error', "⚠️ Impossible de joindre GitLab (réseau/SSL/timeout).");
                    return $this->redirectToRoute('gitlab_url');
                }

                $enc = $crypto->encrypt($plainToken);
                $utilisateur->setGitlabTokenCipher($enc['cipher']);
                $utilisateur->setGitlabTokenNonce($enc['nonce']);
            }
            else {

                if ($gitlab->isPrivateProjectUrl($url)) {
                    $this->addFlash('error', "Ce dépôt est privé. Ajoutez un token GitLab.");
                    return $this->redirectToRoute('gitlab_url');
                }

                try {
                    $gitlab->assertProjectReachable($url, null);
                } catch (\Throwable $e) {
                    $this->addFlash('error', "Aucun dépôt GitLab connu à cette adresse.");
                    return $this->redirectToRoute('gitlab_url');
                }

                $utilisateur->setGitlabTokenCipher(null);
                $utilisateur->setGitlabTokenNonce(null);
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
    public function liste(GitlabApiService $gitlab, GitlabTokenCryptoService $crypto): Response
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

        $token = $crypto->decrypt($utilisateur->getGitlabTokenCipher(), $utilisateur->getGitlabTokenNonce());

        $files = [];
        $page = 1;

        do {
            $apiUrl = "https://$host/api/v4/projects/$projectId/repository/tree?recursive=true&ref=$branch&per_page=100&page=$page";

            try {
                $result = $gitlab->request($apiUrl, $token);
            } catch (GitlabApiException $e) {
                $code = $e->getStatusCode();

                if ($code === 401) {
                    $this->addFlash('error', "❌ Votre token GitLab est invalide ou expiré. Merci de le reconfigurer.");
                    return $this->redirectToRoute('gitlab_url');
                }

                if ($code === 403) {
                    $this->addFlash('error', "⛔ Accès refusé au dépôt (droits insuffisants). Vérifiez que votre compte est membre du projet.");
                    return $this->redirectToRoute('gitlab_url');
                }

                if ($code === 404) {
                    $this->addFlash('error', "❌ Projet introuvable : l’URL ne pointe vers aucun dépôt.");
                    return $this->redirectToRoute('gitlab_url');
                }

                $this->addFlash('error', "⚠️ Erreur GitLab (HTTP $code).");
                return $this->redirectToRoute('gitlab_url');

            } catch (\RuntimeException $e) {
                $this->addFlash('error', "⚠️ Impossible de joindre GitLab (réseau/SSL/timeout).");
                return $this->redirectToRoute('gitlab_url');
            }



            if (!is_array($result) || count($result) === 0) break;

            $files = array_merge($files, $result);
            $page++;

        } while (true);

        if ($files === null) {
            return new Response("Erreur lors de la récupération depuis GitLab", 500);
        }

        $files = $gitlab->filterValidYamlFiles($files, $host, $projectId, $branch, $token);

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
    public function fichier(string $path, GitlabApiService $gitlab, GitlabTokenCryptoService $crypto): Response
    {
        $utilisateur = $this->getUser();

        if ($utilisateur->getGitlabUrl() === null) {
            return $this->redirectToRoute('gitlab_url');
        }

        $parsed = $gitlab->parseGitlabUrl($utilisateur->getGitlabUrl());

        if (!$parsed) {
            $this->addFlash('error', "URL GitLab invalide.");
            return $this->redirectToRoute('gitlab_url');
        }

        $host = $parsed['host'];
        $projectId = $parsed['projectId'];
        $branch = $parsed['branch'];

        $token = $crypto->decrypt($utilisateur->getGitlabTokenCipher(), $utilisateur->getGitlabTokenNonce());

        $encodedPath = rawurlencode($path);

        // URL GitLab RAW file API
        $rawUrl = "https://$host/api/v4/projects/$projectId/repository/files/$encodedPath/raw?ref=$branch";

        try {
            $content = $gitlab->request($rawUrl, $token);
        } catch (GitlabApiException $e) {
            $code = $e->getStatusCode();
            if (in_array($code, [401, 403], true)) {
                return new Response("Accès GitLab refusé (token manquant/incorrect ou droits insuffisants).", 403);
            }
            return new Response("Erreur GitLab (HTTP $code).", 500);
        }

        if (!is_string($content)) {
            return new Response("Impossible de récupérer le fichier.", 500);
        }

        return new Response($content);
    }

    #[IsGranted("ROLE_USER")]
    #[\Symfony\Component\Routing\Attribute\Route("/gitlab/ajouter_gitlab_file", name: 'ajouterAuRepertoireGitlab', methods: ['GET', 'POST'])]
    public function ajouterAuRepertoire(Request $request, EntityManagerInterface $entityManager, GitlabApiService $gitlab, GitlabTokenCryptoService $crypto): Response
    {
        $pathFile = $request->query->get('path');
        $nameFile = basename($pathFile);

        if (!$pathFile) {
            $this->addFlash('error', 'Fichier GitLab non précisé.');
            return $this->redirectToRoute('gitlab_fichiers');
        }

        $utilisateur = $this->getUser();

        if ($utilisateur->getGitlabUrl() === null) {
            return $this->redirectToRoute('gitlab_url');
        }

        $parsed = $gitlab->parseGitlabUrl($utilisateur->getGitlabUrl());

        if (!$parsed) {
            $this->addFlash('error', "URL GitLab invalide.");
            return $this->redirectToRoute('gitlab_url');
        }

        $host = $parsed['host'];
        $projectId = $parsed['projectId'];
        $branch = $parsed['branch'];

        $token = $crypto->decrypt($utilisateur->getGitlabTokenCipher(), $utilisateur->getGitlabTokenNonce());

        $rawUrl = "https://$host/api/v4/projects/$projectId/repository/files/".rawurlencode($pathFile)."/raw?ref=$branch";

        try {
            $bodyFile = $gitlab->request($rawUrl, $token);
        } catch (GitlabApiException $e) {
            $code = $e->getStatusCode();
            if (in_array($code, [401, 403], true)) {
                $this->addFlash('error', "Accès GitLab refusé (token manquant/incorrect ou droits insuffisants).");
                return $this->redirectToRoute('gitlab_url');
            }
            $this->addFlash('error', "Erreur GitLab (HTTP $code).");
            return $this->redirectToRoute('gitlab_fichiers');
        }

        if (!is_string($bodyFile) || trim($bodyFile) === '') {
            $this->addFlash('error', 'Impossible de récupérer le contenu du fichier.');
            return $this->redirectToRoute('gitlab_fichiers');
        }

        $yamlFile = new YamlFile();
        $yamlFile->setNameFile($nameFile);
        $yamlFile->setBodyFile($bodyFile);
        $yamlFile->setUtilisateurYamlfile($utilisateur);

        $form = $this->createForm(AjouterBiblioRepertoireType::class, null, [
            'method' => 'POST',
            'action' => $this->generateUrl('ajouterAuRepertoireGitlab', ['path' => $pathFile]),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $repertoire = $data['repertoire'];

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

        return $this->render('gitlab/ajouterAuRepertoire.html.twig', [
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

        $utilisateur->setGitlabUrl(null);
        $utilisateur->setGitlabTokenCipher(null);
        $utilisateur->setGitlabTokenNonce(null);

        $em->persist($utilisateur);
        $em->flush();

        $this->addFlash('success', 'URL GitLab supprimée.');

        return $this->redirectToRoute('gitlab_url');
    }

}
