<?php

namespace App\Controller;

use App\Entity\Repertoire;
use App\Entity\Utilisateur;
use App\Form\UtilisateurType;
use App\Repository\UtilisateurRepository;
use App\Repository\YamlFileRepository;
use App\Service\FlashMessageHelperInterface;
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
        return $this->render('base.html.twig');
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
            $repertoire->setUtilisateurId($utilisateur);
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

    #[IsGranted(new Expression("is_granted('ROLE_ADMIN')"))]
    #[Route('/panneauadmin', name: 'panneauAdmin', methods: ['GET'])]
    public function panneauAdmin(): Response
    {
        return $this->render('utilisateur/panneauAdmin.html.twig');
    }

    #[IsGranted(new Expression("is_granted('ROLE_ADMIN')"))]
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

    #[IsGranted(attribute: 'CHANGE_ROLE', subject: 'utilisateur')]
    #[Route('/panneauadmin/listeutilisateurs/{login}/{role}', name: 'changeRole', options: ['expose' => true], methods: ['POST'])]
    public function changerRole(?Utilisateur $utilisateur, ?string $role, EntityManagerInterface $entityManager): Response
    {
        if ($utilisateur != null) {
            if ($role === "~") {
                $utilisateur->setRoles([]);
            } else {
                $utilisateur->setRoles([$role]);
            }
            $entityManager->flush();
        } else {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }
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
            $this->addFlash('danger', "L'utilisateur n'existe pas");
            return $this->redirectToRoute('listeUtilisateurs');
        } else if($utilisateur->getProxmoxVmid() !== null) {
            $this->addFlash('danger', "Cette Utilisateur à déjà une VM actif");
            return $this->redirectToRoute('listeUtilisateurs');
        } else {
            $vmId = $proxmoxService->cloneUserVM($utilisateur->getLogin());
            $utilisateur->setProxmoxVmid($vmId);
            $entityManager->flush();

            $this->addFlash('success', "Une VM a été ajouté à l'utilisateur" . $utilisateur->getLogin());
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
            $this->addFlash('danger', "L'utilisateur n'existe pas");
            return $this->redirectToRoute('listeUtilisateurs');
        } else if($utilisateur->getProxmoxVmid() === null) {
            $this->addFlash('danger', "Cette Utilisateur n'a pas de VM actif");
            return $this->redirectToRoute('listeUtilisateurs');
        } else {
            $proxmoxService->deleteVM($utilisateur->getLogin());
            $utilisateur->setProxmoxVmid(null);
            $entityManager->flush();

            $this->addFlash('success', "La VM a été supprimé à l'utilisateur" . $utilisateur->getLogin());
            return $this->redirectToRoute('listeUtilisateurs');
        }
    }

}
