<?php

namespace App\Controller;

use App\Entity\Groupe;
use App\Entity\Repertoire;
use App\Entity\Utilisateur;
use App\Entity\UtilisateurGroupe;
use App\Entity\VirtualMachine;
use App\Form\GitlabUrlType;
use App\Form\UtilisateurType;
use App\Repository\UtilisateurRepository;
use App\Service\FlashMessageHelperInterface;
use App\Service\GitlabApiException;
use App\Service\GitlabApiService;
use App\Service\GitlabSyncService;
use App\Service\GitlabTokenCryptoService;
use App\Service\ProxmoxService;
use App\Service\UtilisateurManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class UtilisateurController extends AbstractController
{
    public function __construct(UtilisateurRepository $repository)
    {
        $this->repository = $repository;
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('accueil.html.twig');
    }

    #[Route('/inscription', name: 'inscription', methods: ['GET', 'POST'])]
    public function inscrire(Request                     $request,
                             EntityManagerInterface      $entityManager,
                             FlashMessageHelperInterface $flashMessageHelperInterface,
                             UtilisateurManagerInterface $utilisateurManager): Response
    {
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('index');
        }

        $utilisateur = new Utilisateur();


        $form = $this->createForm(UtilisateurType::class,
            $utilisateur,
            ['method' => 'POST', 'action' => $this->generateUrl('inscription')]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $utilisateurManager->processNewUtilisateur($utilisateur, $form["plainPassword"]->getData());
            $repertoire = new Repertoire();
            $repertoire->setUtilisateurRepertoire($utilisateur);
            $repertoire->setName('Répertoire personnel');

            $entityManager->persist($repertoire);
            $entityManager->persist($utilisateur);
            $entityManager->flush();
            $this->addFlash('success', 'Inscription réussie !');

            return $this->redirectToRoute('index');
        }

        $flashMessageHelperInterface->addFormErrorsAsFlash($form);

        return $this->render('utilisateur/inscription.html.twig', ['formulaireUtilisateur' => $form]);
    }

    #[Route('/connexion', name: 'connexion', methods: ['GET', 'POST'])]
    public function connecter(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('index');
        }

        $lastUsername = $authenticationUtils->getLastUsername();
        return $this->render('utilisateur/connexion.html.twig', ['lastUsername' => $lastUsername]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/panneauadmin', name: 'panneauAdmin', methods: ['GET'])]
    public function panneauAdmin(): Response
    {
        return $this->render('utilisateur/panneauAdmin.html.twig');
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/panneauadmin/listeutilisateurs', name: 'listeUtilisateurs', methods: ['GET'])]
    public function listeUtilisateurs(): Response
    {
        $utilisateurs = $this->repository->findAll();
        $appRoles = $this->getParameter('security.role_hierarchy.roles');
        return $this->render('utilisateur/listeUtilisateurs.html.twig', [
            'utilisateurs' => $utilisateurs,
            'appRoles' => $appRoles
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/panneauadmin/listeutilisateurs/{login}/{role}', name: 'changeRole', options: ['expose' => true], methods: ['POST'])]
    public function changerRole(?Utilisateur $utilisateur, ?string $role, EntityManagerInterface $entityManager): Response
    {
        if ($role === "~") {
            $utilisateur->setRoles([]);
        } else {
            $utilisateur->setRoles([$role]);
        }
        $entityManager->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN')"))]
    #[Route('/panneauadmin/listeutilisateurs/{login}/creationVM', name: 'creerVmUtilisateur', methods: ['GET'])]
    public function creerVmUtilisateur(?Utilisateur $utilisateur, ProxmoxService $proxmoxService, EntityManagerInterface $entityManager) : Response {
        if($utilisateur === null) {
            $this->addFlash('error', "L'utilisateur n'existe pas");
            return $this->redirectToRoute('listeUtilisateurs');
        } else if($utilisateur->getVm()->getVmId()() !== null) {
            $this->addFlash('error', "Cette Utilisateur à déjà une VM actif");
            return $this->redirectToRoute('listeUtilisateurs');
        } else {
            $vmId = $proxmoxService->cloneVm($utilisateur->getLogin(), $utilisateur->getVm()->getId());
            $utilisateur->getVm()->setVmId($vmId);
            $entityManager->flush();

            $this->addFlash('success', "Une VM a été ajouté à l'utilisateur " . $utilisateur->getLogin());
            return $this->redirectToRoute('listeUtilisateurs');
        }
    }

    /**
     * @throws TransportExceptionInterface
     */
    #[IsGranted(new Expression("is_granted('ROLE_ADMIN')"))]
    #[Route('/panneauadmin/listeutilisateurs/{login}/suppressionVM', name: 'supprimerVmUtilisateur', methods: ['GET'])]
    public function suppressionVmUtilisateur(?Utilisateur $utilisateur, ProxmoxService $proxmoxService, EntityManagerInterface $entityManager) : Response {
        if($utilisateur === null) {
            $this->addFlash('error', "L'utilisateur n'existe pas");
            return $this->redirectToRoute('listeUtilisateurs');
        } else if($utilisateur->getVm()->getVmId()() === null) {
            $this->addFlash('error', "Cette Utilisateur n'a pas de VM actif");
            return $this->redirectToRoute('listeUtilisateurs');
        } else {
            $proxmoxService->deleteVM($utilisateur->getVm()->getVmId()());
            $utilisateur->getVm()->setVmId(null);
            $utilisateur->getVm()->setVmStatus(null);
            $utilisateur->getVm()->setVmIp(null);
            $utilisateur->getVm()->setDeleteVmAt(null);
            $entityManager->flush();

            $this->addFlash('success', "La VM a été supprimé à l'utilisateur " . $utilisateur->getLogin());
            return $this->redirectToRoute('listeUtilisateurs');
        }
    }

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

            return $this->redirectToRoute('repertoire');
        }

        return $this->render('utilisateur/url.html.twig', [
            'form' => $form,
        ]);
    }

    #[IsGranted("ROLE_USER")]
    #[Route('/gitlab/supprimer-url', name: 'gitlab_supprimer_url')]
    public function supprimerUrl(
        EntityManagerInterface $em,
        GitlabSyncService $sync,
    ): Response {
        $u = $this->getUser();

        $sync->deleteAllFromGitlabFiles($u);

        $u->setGitlabUrl(null);
        $u->setGitlabTokenCipher(null);
        $u->setGitlabTokenNonce(null);
        $u->setGitlabLastCommitSha(null);

        $em->persist($u);
        $em->flush();

        $this->addFlash('success', 'URL GitLab supprimée.');
        return $this->redirectToRoute('repertoire');
    }

    #[IsGranted('ROLE_ETUDIANT')]
    #[Route('/choisir-classe', name: 'choisir_classe', methods: ['POST'])]
    public function choisirClasse(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('choisir_classe', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $utilisateur = $this->getUser();
        $classeId = $request->request->get('classe_id');

        $groupe = $em->getRepository(Groupe::class)->find($classeId);

        if ($groupe) {
            $utilisateur->setClasse($groupe->getNom());
            $dejaPresent = $em->getRepository(UtilisateurGroupe::class)->findOneBy([
                'utilisateur' => $utilisateur,
                'groupe' => $groupe,
            ]);

            if (!$dejaPresent) {
                $ug = new UtilisateurGroupe();
                $ug->setUtilisateur($utilisateur);
                $ug->setGroupe($groupe);
                $em->persist($ug);
            }

            $em->flush();
            $em->flush();
            $request->getSession()->remove('show_classe_popup');
            $this->addFlash('success', 'Classe assignée avec succès.');
        }

        return $this->redirectToRoute('mes_groupes');
    }

    #[IsGranted('ROLE_ETUDIANT')]
    #[Route('/ignorer-classe-popup', name: 'ignorer_classe_popup', methods: ['GET'])]
    public function ignorerClassePopup(Request $request): Response
    {
        $request->getSession()->remove('show_classe_popup');

        return $this->redirectToRoute('index');
    }

    #[IsGranted('ROLE_PROFESSEUR')]
    #[Route('/liste-etudiants', name: 'liste_etudiants', methods: ['GET'])]
    public function listeEtudiants(): Response {

        $utilisateurs = $this->repository->findByRole('ROLE_ETUDIANT');
        return $this->render('utilisateur/listeEtudiants.html.twig', [
            'utilisateurs' => $utilisateurs,
        ]);
    }

    #[IsGranted('ROLE_PROFESSEUR')]
    #[Route('/liste-etudiants/creer-depuis-selection', name: 'creer_groupe_depuis_selection', methods: ['POST'])]
    public function creerGroupeDepuisSelection(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('creer_groupe_selection', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $nom = $request->request->get('nom');
        $membresIds = array_filter(explode(',', $request->request->get('membres_ids')));

        $groupe = new Groupe();
        $groupe->setNom($nom);
        $groupe->setEtreChef($this->getUser());
        $groupe->setVm(new VirtualMachine());

        $repertoire = new Repertoire();
        $repertoire->setGroupeRepertoire($groupe);
        $repertoire->setName('Répertoire groupe');

        $em->persist($groupe);
        $em->persist($repertoire);

        foreach ($membresIds as $membreId) {
            $membre = $em->getRepository(Utilisateur::class)->find($membreId);
            if (!$membre) continue;

            $ug = new UtilisateurGroupe();
            $ug->setUtilisateur($membre);
            $ug->setGroupe($groupe);
            $em->persist($ug);
        }

        $em->flush();

        $this->addFlash('success', "Groupe \"$nom\" créé avec " . count($membresIds) . " membre(s).");
        return $this->redirectToRoute('liste_etudiants');
    }


}