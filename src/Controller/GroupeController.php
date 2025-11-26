<?php

namespace App\Controller;

use App\Entity\Groupe;
use App\Entity\Repertoire;
use App\Entity\Utilisateur;
use App\Form\AjouterMembreGroupeType;
use App\Form\GroupeType;
use App\Repository\GroupeRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class GroupeController extends AbstractController
{
    #[IsGranted("ROLE_USER")]
    #[Route('/mes_groupes', name: 'mes_groupes', methods: ['GET', 'POST'])]
    public function mesGroupes(Request $request, EntityManagerInterface $em): Response
    {
        $utilisateur = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN')) {
            $groupesMembre = $em->getRepository(Groupe::class)->findAll();
        } else {
            $groupesMembre = $utilisateur->getUtilisateurGroupe();
        }

        $groupe = new Groupe();
        $formGroupe = $this->createForm(GroupeType::class, $groupe);
        $formGroupe->handleRequest($request);

        if ($formGroupe->isSubmitted() && $formGroupe->isValid()) {
            $repertoire = new Repertoire();
            $repertoire->setGroupeRepertoire($groupe);
            $repertoire->setName('Répertoire groupe');

            $groupe->setEtreChef($utilisateur);
            $groupe->addUtilisateurGroupe($utilisateur);
            $em->persist($groupe);
            $em->persist($repertoire);
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
        $formAjouterMembre = $this->createForm(AjouterMembreGroupeType::class, null, [
            'groupe' =>  $groupe,
        ]);
        $formAjouterMembre->handleRequest($request);

        if ($this->isGranted('GROUPE_EDIT', $groupe) && $formAjouterMembre->isSubmitted() && $formAjouterMembre->isValid()) {
            $data = $formAjouterMembre->getData();
            $nouveauMembre = $data['utilisateur'];

            if ($groupe->contientMembre($nouveauMembre)) {
                $this->addFlash('error', 'Cet utilisateur est déjà dans le groupe.');
            } else {
                $groupe->addUtilisateurGroupe($nouveauMembre);
                $em->persist($groupe);
                $em->flush();
                $this->addFlash('success', 'Utilisateur ajouté avec succès au groupe.');
            }

            return $this->redirectToRoute('voir_groupe', ['id' => $groupe->getId()]);
        }

        return $this->render('groupe/listeUtilisateurGroupe.html.twig', [
            'groupe' => $groupe,
            'formAjouterMembre' => $formAjouterMembre,
        ]);
    }

    #[IsGranted(attribute: 'GROUPE_EDIT', subject: 'groupe')]
    #[Route('/groupe/{id}/supprimer', name: 'supprimer_groupe', methods: ['POST'])]
    public function supprimerGroupe(Groupe $groupe, EntityManagerInterface $em): Response
    {
        $utilisateur = $this->getUser();

        $em->remove($groupe);
        $em->flush();

        $this->addFlash('success', sprintf('Le groupe "%s" a été supprimé avec succès.', $groupe->getNom()));
        return $this->redirectToRoute('mes_groupes');
    }

    #[IsGranted(attribute: 'GROUPE_EDIT', subject: 'groupe')]
    #[Route('/groupe/{id}/supprimer_membre/{login}', name: 'supprimer_membre_groupe', methods: ['POST'])]
    public function supprimerMembre(
        Groupe $groupe,
        string $login,
        EntityManagerInterface $em,
    ): Response {
        $utilisateurRepository = $em->getRepository(Utilisateur::class);

        $membre = $utilisateurRepository->findOneBy(['login' => $login]);
        if (!$membre) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('voir_groupe', ['id' => $groupe->getId()]);
        }

        if (!$groupe->contientMembre($membre)) {
            $this->addFlash('error', 'Cet utilisateur ne fait pas partie de ce groupe.');
            return $this->redirectToRoute('voir_groupe', ['id' => $groupe->getId()]);
        }

        $groupe->removeUtilisateurGroupe($membre);
        $em->flush();

        $this->addFlash('success', sprintf('L’utilisateur "%s" a été retiré du groupe.', $membre->getLogin()));
        return $this->redirectToRoute('voir_groupe', ['id' => $groupe->getId()]);
    }


    #[IsGranted(attribute: 'GROUPE_LEAVE', subject: 'groupe')]
    #[Route('/groupe/{id}/quitter', name: 'quitter_groupe', methods: ['POST'])]
    public function quitterGroupe(Groupe $groupe, EntityManagerInterface $em): Response
    {
        $utilisateur = $this->getUser();

        if ($groupe->contientMembre($utilisateur)) {
            $groupe->removeUtilisateurGroupe($utilisateur);
            $em->flush();
            $this->addFlash('success', 'Vous avez quitté le groupe.');
        }

        return $this->redirectToRoute('mes_groupes');
    }

}
