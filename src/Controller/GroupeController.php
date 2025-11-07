<?php

namespace App\Controller;

use App\Entity\Groupe;
use App\Form\AjouterMembreGroupeType;
use App\Form\GroupeType;
use App\Repository\GroupeRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GroupeController extends AbstractController
{
    #[Route('/mes_groupes', name: 'mes_groupes', methods: ['GET', 'POST'])]
    public function mesGroupes(Request $request, EntityManagerInterface $em): Response
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur) {
            $this->addFlash('error', 'Veuillez vous connecter pour voir vos groupes.');
            return $this->redirectToRoute('connexion');
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            $groupesMembre = $em->getRepository(Groupe::class)->findAll();
        } else {
            $groupesMembre = $utilisateur->getGroupesMembre();
        }

        $groupe = new Groupe();
        $formGroupe = $this->createForm(GroupeType::class, $groupe);
        $formGroupe->handleRequest($request);

        if ($formGroupe->isSubmitted() && $formGroupe->isValid()) {
            $groupe->setUtilisateurChef($utilisateur);
            $groupe->addUtilisateur($utilisateur);
            $em->persist($groupe);
            $em->flush();
            $this->addFlash('success', 'Groupe créé avec succès.');
            return $this->redirectToRoute('mes_groupes');
        }

        return $this->render('groupe/listeGroupe.html.twig', [
            'groupesMembre' => $groupesMembre,
            'formGroupe' => $formGroupe,
        ]);
    }


    #[Route('/groupe/{id}', name: 'voir_groupe', methods: ['GET', 'POST'])]
    public function voirGroupe(
        Groupe $groupe,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $utilisateur = $this->getUser();
        if (!$utilisateur) {
            $this->addFlash('error', 'Veuillez vous connecter.');
            return $this->redirectToRoute('connexion');
        }

        $isChef = $groupe->getUtilisateurChef() === $utilisateur || $this->isGranted('ROLE_ADMIN');

        $formAjouterMembre = $this->createForm(AjouterMembreGroupeType::class);
        $formAjouterMembre->handleRequest($request);

        if ($isChef && $formAjouterMembre->isSubmitted() && $formAjouterMembre->isValid()) {
            $data = $formAjouterMembre->getData();
            $nouveauMembre = $data['utilisateur'];

            if ($groupe->getUtilisateurs()->contains($nouveauMembre)) {
                $this->addFlash('error', 'Cet utilisateur est déjà dans le groupe.');
            } else {
                $groupe->addUtilisateur($nouveauMembre);
                $em->persist($groupe);
                $em->flush();
                $this->addFlash('success', 'Utilisateur ajouté avec succès au groupe.');
            }

            return $this->redirectToRoute('voir_groupe', ['id' => $groupe->getId()]);
        }

        return $this->render('groupe/listeUtilisateurGroupe.html.twig', [
            'groupe' => $groupe,
            'isChef' => $isChef,
            'formAjouterMembre' => $formAjouterMembre,
        ]);
    }

    #[Route('/groupe/{id}/supprimer', name: 'supprimer_groupe', methods: ['POST'])]
    public function supprimerGroupe(Groupe $groupe, EntityManagerInterface $em): Response
    {
        $utilisateur = $this->getUser();

        if (!$utilisateur) {
            $this->addFlash('error', 'Veuillez vous connecter.');
            return $this->redirectToRoute('connexion');
        }

        if ($groupe->getUtilisateurChef() !== $utilisateur && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Vous n’êtes pas autorisé à supprimer ce groupe.');
            return $this->redirectToRoute('mes_groupes');
        }

        $em->remove($groupe);
        $em->flush();

        $this->addFlash('success', sprintf('Le groupe "%s" a été supprimé avec succès.', $groupe->getNom()));
        return $this->redirectToRoute('mes_groupes');
    }


    #[Route('/groupe/{id}/supprimer_membre/{login}', name: 'supprimer_membre_groupe', methods: ['POST'])]
    public function supprimerMembre(
        Groupe $groupe,
        string $login,
        EntityManagerInterface $em,
        UtilisateurRepository $utilisateurRepository
    ): Response {
        $utilisateurConnecte = $this->getUser();

        if (!$utilisateurConnecte) {
            $this->addFlash('error', 'Veuillez vous connecter.');
            return $this->redirectToRoute('connexion');
        }

        $isChef = $groupe->getUtilisateurChef() === $utilisateurConnecte;
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        if (!$isChef && !$isAdmin) {
            $this->addFlash('error', 'Vous n’êtes pas autorisé à supprimer des membres de ce groupe.');
            return $this->redirectToRoute('voir_groupe', ['id' => $groupe->getId()]);
        }

        $membre = $utilisateurRepository->findOneBy(['login' => $login]);
        if (!$membre) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('voir_groupe', ['id' => $groupe->getId()]);
        }

        if (!$groupe->getUtilisateurs()->contains($membre)) {
            $this->addFlash('error', 'Cet utilisateur ne fait pas partie de ce groupe.');
            return $this->redirectToRoute('voir_groupe', ['id' => $groupe->getId()]);
        }

        $groupe->removeUtilisateur($membre);
        $em->flush();

        $this->addFlash('success', sprintf('L’utilisateur "%s" a été retiré du groupe.', $membre->getLogin()));
        return $this->redirectToRoute('voir_groupe', ['id' => $groupe->getId()]);
    }


    #[Route('/groupe/{id}/quitter', name: 'quitter_groupe', methods: ['POST'])]
    public function quitterGroupe(Groupe $groupe, EntityManagerInterface $em): Response
    {
        $utilisateur = $this->getUser();

        if (!$utilisateur) {
            $this->addFlash('error', 'Veuillez vous connecter.');
            return $this->redirectToRoute('connexion');
        }

        // Le chef ne peut pas "quitter" son propre groupe
        if ($groupe->getUtilisateurChef() === $utilisateur) {
            $this->addFlash('error', 'Le chef ne peut pas quitter le groupe. Supprimez-le à la place.');
            return $this->redirectToRoute('voir_groupe', ['id' => $groupe->getId()]);
        }

        if ($groupe->getUtilisateurs()->contains($utilisateur)) {
            $groupe->removeUtilisateur($utilisateur);
            $em->flush();
            $this->addFlash('success', 'Vous avez quitté le groupe.');
        }

        return $this->redirectToRoute('mes_groupes');
    }

}
