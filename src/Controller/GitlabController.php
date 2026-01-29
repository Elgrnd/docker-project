<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\UtilisateurFileRepertoire;
use App\Form\AjouterBiblioRepertoireType;
use App\Form\GitlabUrlType;
use App\Service\GitlabApiException;
use App\Service\GitlabApiService;
use App\Service\GitlabSyncService;
use App\Service\GitlabTokenCryptoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GitlabController extends AbstractController
{
    #[IsGranted("ROLE_USER")]
    #[Route('/gitlab/url', name: 'gitlab_url')]
    public function gitlabUrl(
        Request $request,
        EntityManagerInterface $em,
        GitlabApiService $gitlab,
        GitlabTokenCryptoService $crypto,
        GitlabSyncService $sync
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
                    if ($code === 401) $this->addFlash('error', "Token GitLab invalide ou expiré.");
                    elseif ($code === 403) $this->addFlash('error', "Token valide mais accès refusé au dépôt (droits insuffisants).");
                    elseif ($code === 404) $this->addFlash('error', "Projet introuvable : vérifiez l’URL GitLab.");
                    elseif ($code >= 500) $this->addFlash('error', "GitLab indisponible (HTTP $code).");
                    else $this->addFlash('error', "Erreur GitLab (HTTP $code).");

                    return $this->redirectToRoute('gitlab_url');

                } catch (\RuntimeException $e) {
                    $this->addFlash('error', "Impossible de joindre GitLab (réseau/SSL/timeout).");
                    return $this->redirectToRoute('gitlab_url');
                }

                $enc = $crypto->encrypt($plainToken);
                $utilisateur->setGitlabTokenCipher($enc['cipher']);
                $utilisateur->setGitlabTokenNonce($enc['nonce']);
            } else {
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

            try {
                $res = $sync->syncUtilisateur($utilisateur, maxBytes: 10 * 1024 * 1024);

                $this->addFlash('success', 'URL GitLab enregistrée et fichiers mis en cache !');
                $this->addFlash('success', "Cache GitLab : {$res['imported']} fichier(s) importé(s).");

                if ($res['ignoredTooBig'] > 0) {
                    $this->addFlash('warning', "{$res['ignoredTooBig']} fichier(s) ignoré(s) (> 10 Mo).");
                }
                if ($res['ignoredNotAllowed'] > 0) {
                    $this->addFlash('warning', "{$res['ignoredNotAllowed']} fichier(s) ignoré(s) (extension/MIME non autorisés).");
                }

            } catch (\Throwable $e) {
                $this->addFlash('warning', "URL GitLab enregistrée, mais la mise en cache a échoué : " . $e->getMessage());
                $this->addFlash('warning', "Vous pouvez réessayer avec le bouton “Actualiser (cache)”.");
            }

            return $this->redirectToRoute('gitlab_fichiers');
        }

        return $this->render('gitlab/url.html.twig', [
            'form' => $form,
        ]);
    }


    #[IsGranted("ROLE_USER")]
    #[Route('/gitlab/fichiers', name: 'gitlab_fichiers')]
    public function liste(
        EntityManagerInterface $em,
        GitlabApiService $gitlab,
        GitlabSyncService $sync,
    ): Response {
        $u = $this->getUser();

        if ($u->getGitlabUrl() === null) {
            return $this->redirectToRoute('gitlab_url');
        }

        $parsed = $gitlab->parseGitlabUrl($u->getGitlabUrl());
        if (!$parsed) {
            $this->addFlash('error', "URL GitLab invalide.");
            return $this->redirectToRoute('gitlab_url');
        }

        $latestSha = null;
        try {
            $latestSha = $sync->getLatestShaForUser($u);
        } catch (\Throwable $e) {
            // GitLab down => on affiche quand même la BD
        }

        if ($u->getGitlabLastCommitSha() === null) {
            $this->addFlash('info', "Aucune mise en cache GitLab effectuée. Cliquez sur Actualiser pour importer.");
        } elseif ($latestSha !== null && $latestSha !== $u->getGitlabLastCommitSha()) {
            $this->addFlash('warning', "Votre dépôt GitLab a changé depuis la dernière mise en cache. Cliquez sur Actualiser.");
        }

        $tree = $sync->buildTreeFromDatabase($u);

        return $this->render('gitlab/arborescence.html.twig', [
            'tree' => $tree,
            'namespace' => $parsed['namespace'],
            'project' => $parsed['project'],
            'branch' => $parsed['branch'],
            'host' => $parsed['host'],
        ]);
    }

    #[IsGranted("ROLE_USER")]
    #[Route('/gitlab/sync', name: 'gitlab_sync', methods: ['POST'])]
    public function sync(
        Request $request,
        GitlabSyncService $sync
    ): Response {
        if (!$this->isCsrfTokenValid('gitlab_sync', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('gitlab_fichiers');
        }

        $u = $this->getUser();
        if ($u->getGitlabUrl() === null) {
            return $this->redirectToRoute('gitlab_url');
        }

        try {
            $res = $sync->syncUtilisateur($u, maxBytes: 10 * 1024 * 1024);

        } catch (GitlabApiException $e) {
            $code = $e->getStatusCode();

            if ($code === 401) {
                $this->addFlash('error', "Votre token GitLab est invalide ou expiré. Merci de le reconfigurer.");
                return $this->redirectToRoute('gitlab_url');
            }

            if ($code === 403) {
                $this->addFlash('error', "Accès refusé au dépôt (droits insuffisants). Vérifiez que votre compte est membre du projet.");
                return $this->redirectToRoute('gitlab_url');
            }

            if ($code === 404) {
                $this->addFlash('error', "Projet introuvable : l’URL ne pointe vers aucun dépôt. Merci de corriger l’URL.");
                return $this->redirectToRoute('gitlab_url');
            }

            if ($code >= 500) {
                $this->addFlash('error', "GitLab est indisponible pour le moment (HTTP $code). Réessayez plus tard.");
                return $this->redirectToRoute('gitlab_fichiers');
            }

            $this->addFlash('error', "Erreur GitLab (HTTP $code).");
            return $this->redirectToRoute('gitlab_fichiers');

        } catch (\RuntimeException $e) {
            $this->addFlash('error', "Impossible de joindre GitLab (réseau/SSL/timeout). Réessayez plus tard.");
            return $this->redirectToRoute('gitlab_fichiers');

        } catch (\Throwable $e) {
            $this->addFlash('error', "Erreur inattendue lors de la synchronisation.");
            return $this->redirectToRoute('gitlab_fichiers');
        }

        $this->addFlash('success', "Cache GitLab mis à jour : {$res['imported']} fichier(s) importé(s).");

        if ($res['ignoredTooBig'] > 0) {
            $this->addFlash('warning', "{$res['ignoredTooBig']} fichier(s) ignoré(s) (> 10 Mo).");
        }
        if ($res['ignoredNotAllowed'] > 0) {
            $this->addFlash('warning', "{$res['ignoredNotAllowed']} fichier(s) ignoré(s) (extension/MIME non autorisés).");
        }

        return $this->redirectToRoute('gitlab_fichiers');
    }

    #[IsGranted("ROLE_USER")]
    #[Route('/gitlab/importer/{id}', name: 'gitlab_importer', methods: ['GET', 'POST'])]
    public function importer(
        ?File $fileGitlab,
        Request $request,
        EntityManagerInterface $em,
        GitlabSyncService $sync,
    ): Response {
        $u = $this->getUser();

        if (!$fileGitlab) {
            $this->addFlash('error', 'Fichier introuvable.');
            return $this->redirectToRoute('gitlab_fichiers');
        }

        if ($fileGitlab->getUtilisateurFile() !== $u || !$fileGitlab->isFromGitlab()) {
            $this->addFlash('error', 'Accès interdit.');
            return $this->redirectToRoute('gitlab_fichiers');
        }

        $form = $this->createForm(AjouterBiblioRepertoireType::class, null, [
            'method' => 'POST',
            'action' => $this->generateUrl('gitlab_importer', ['id' => $fileGitlab->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $repertoire = $data['repertoire'];

            $repo = $em->getRepository(UtilisateurFileRepertoire::class);

            // ✅ CHECK AVANT CLONE (zéro écriture disque si doublon)
            if ($repo->existsFileUtilisateur($u->getId(), $fileGitlab->getNameFile(), $repertoire->getId())) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà dans ce répertoire.',
                    $fileGitlab->getNameFile()
                ));
                return $this->redirectToRoute('gitlab_importer', ['id' => $fileGitlab->getId()]);
            }

            $newFile = $sync->cloneFromGitlabToUserSpace($fileGitlab, $u);

            $ufr = new UtilisateurFileRepertoire();
            $ufr->setUtilisateur($u);
            $ufr->setRepertoire($repertoire);
            $ufr->setFile($newFile);

            $em->persist($newFile);
            $em->persist($ufr);
            $em->flush();

            $this->addFlash('success', 'Fichier importé dans votre espace personnel.');
            return $this->redirectToRoute('gitlab_fichiers');
        }

        return $this->render('gitlab/ajouterAuRepertoire.html.twig', [
            'formulaire' => $form->createView(),
            'fileBiblio' => $fileGitlab,
            'routeAnnuler' => 'gitlab_fichiers',
        ]);
    }

    #[IsGranted("ROLE_USER")]
    #[Route('/gitlab/supprimer-url', name: 'gitlab_supprimer_url')]
    public function supprimerUrl(
        EntityManagerInterface $em,
        GitlabSyncService $sync,
    ): Response {
        $u = $this->getUser();
        if ($u === null) return $this->redirectToRoute('gitlab_url');

        $sync->deleteAllFromGitlabFiles($u);

        $u->setGitlabUrl(null);
        $u->setGitlabTokenCipher(null);
        $u->setGitlabTokenNonce(null);
        $u->setGitlabLastCommitSha(null);

        $em->persist($u);
        $em->flush();

        $this->addFlash('success', 'URL GitLab supprimée.');
        return $this->redirectToRoute('gitlab_url');
    }
}
