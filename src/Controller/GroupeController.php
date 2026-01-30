<?php

namespace App\Controller;

use App\Entity\Groupe;
use App\Entity\Repertoire;
use App\Entity\Utilisateur;
use App\Entity\UtilisateurGroupe;
use App\Form\AjouterMembreGroupeType;
use App\Form\GroupeType;
use App\Repository\GroupeRepository;
use App\Repository\UtilisateurRepository;
use App\Service\ProxmoxService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
            $ug = $groupe->addUtilisateurGroupe($utilisateur);
            $ug->setRole("GROUPE_CHEF");

            $em->persist($groupe);
            $em->persist($repertoire);
            $em->persist($ug);
            $em->flush();
            $this->addFlash('success', 'Groupe créé avec succès.');
            return $this->redirectToRoute('mes_groupes');
        }

        return $this->render('groupe/listeGroupe.html.twig', [
            'groupesMembre' => $groupesMembre,
            'formGroupe' => $formGroupe,
        ]);
    }

    #[IsGranted("GROUPE_VIEW", subject: 'groupe')]
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

        if ($this->isGranted('GROUPE_MODERATE', $groupe) && $formAjouterMembre->isSubmitted() && $formAjouterMembre->isValid()) {
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

    #[IsGranted(attribute: 'GROUPE_MODERATE', subject: 'groupe')]
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

    #[IsGranted('GROUPE_EDIT', subject: 'groupe')]
    #[Route('/groupe/{id}/changer_role/{login}/{role}', name: 'changeRoleGroupe', options: ['expose' => true], methods: ['POST'])]
    public function changerRoleGroupe(
        Groupe $groupe,
        string $login,
        string $role,
        EntityManagerInterface $em
    ): Response {
        $utilisateur = $em->getRepository(Utilisateur::class)->findOneBy(['login' => $login]);
        if (!$utilisateur) {
            return new JsonResponse(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $ug = $em->getRepository(UtilisateurGroupe::class)
            ->findOneBy(['groupe' => $groupe, 'utilisateur' => $utilisateur]);

        if (!$ug) {
            return new JsonResponse(['error' => 'Utilisateur non membre du groupe'], Response::HTTP_BAD_REQUEST);
        }

        $ug->setRole($role);

        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[IsGranted('ROLE_PROFESSEUR')]
    #[Route('/groupe/{id}/vm', name: 'create_vm_groupe', methods: ['POST'])]
    public function createVmGroup(
        Groupe $groupe,
        EntityManagerInterface $entityManager,
        ProxmoxService $proxmoxService): Response
    {
        if(!$groupe) {
            $this->addFlash('error', "Le groupe n'existe pas");
            $this->redirectToRoute("accueil");
        }

        $groupe->getVm()->setVmStatus('creating');
        $entityManager->flush();

        $proxmoxService->cloneGroupVmAsynchrone($groupe->getId());

        $this->addFlash('success', "Vous avez bien créer une VM pour le groupe " .  $groupe->getNom());
        return $this->redirectToRoute('mes_groupes');
    }

    #[IsGranted('ROLE_PROFESSEUR')]
    #[Route('/groupe/{id}/delete', name: 'delete_vm_groupe', methods: ['POST'])]
    public function deleteVmGroup(Groupe $groupe, ProxmoxService $proxmoxService, EntityManagerInterface $entityManager): Response
    {
        if(!$groupe) {
            $this->addFlash('error', "Le groupe n'existe pas");
            $this->redirectToRoute("accueil");
        }

        $vmid = $groupe->getVm()->getVmId();
        try {
            $ok = $proxmoxService->deleteVM($vmid);
            if ($ok) {
                $this->addFlash('success', "VM $vmid supprimée avec succès.");
                $groupe->getVm()->setVmId(null);
                $groupe->getVm()->setVmStatus('none');
                $entityManager->flush();
            } else {
                $this->addFlash('error', "Impossible de supprimer la VM $vmid.");
            }
        } catch (Exception $e) {
            $this->addFlash('error', "Erreur lors de la suppression de la VM $vmid : " . $e->getMessage());
        }

        return $this->redirectToRoute('mes_groupes');
    }

}
