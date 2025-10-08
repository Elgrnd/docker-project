<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\UtilisateurType;
use App\Service\FlashMessageHelperInterface;
use App\Service\UtilisateurManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class UtilisateurController extends AbstractController
{
    #[Route('/', name:'index', methods:['GET'])]
    public function index() {
        return $this->render('base.html.twig');
    }

    #[Route('/inscription', name: 'inscription', methods: ['GET', 'POST'])]
    public function inscrire(Request  $request, EntityManagerInterface $entityManager, FlashMessageHelperInterface $flashMessageHelperInterface, UtilisateurManagerInterface $utilisateurManager): Response
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
            $entityManager->persist($utilisateur);
            $entityManager->flush();
            $this->addFlash('success', 'Inscription réussie !');

            return $this->redirectToRoute('index');
        }

        $flashMessageHelperInterface->addFormErrorsAsFlash($form);

        return $this->render('utilisateur/inscription.html.twig', ['formulaireUtilisateur' => $form]);
    }

    #[Route('/connexion', name: 'connexion', methods: ['GET', 'POST'])]
    public function connecter(AuthenticationUtils $authenticationUtils) : Response
    {
        if ($this->isGranted('ROLE_USER')) {
            return $this->redirectToRoute('index');
        }

        $lastUsername = $authenticationUtils->getLastUsername();
        return $this->render('utilisateur/connexion.html.twig', ['lastUsername' => $lastUsername]);
    }
}
