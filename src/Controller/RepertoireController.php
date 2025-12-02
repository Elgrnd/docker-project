<?php

namespace App\Controller;

use App\Entity\Repertoire;
use App\Repository\RepertoireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;


final class RepertoireController extends AbstractController
{
    #[Route('/repertoire/rename/{id}', name: 'rename_repertoire', options: ["expose" => true], methods: ['POST'])]
    public function index(EntityManagerInterface $entityManager, Repertoire $repertoire, Request $request): Response
    {
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $nouveauNom = $data['name'] ?? null;



        $parent = $repertoire->getParent();
        if ($repertoire->getUtilisateurRepertoire() !=null){
            if ($repertoire->getUtilisateurRepertoire() !== $user) {
                return $this->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas les droits pour renommer ce répertoire'
                ], Response::HTTP_FORBIDDEN);
            }
            $existe = $entityManager->getRepository(Repertoire::class)->verifierNomDejaExistantUtilsateur($nouveauNom, $parent, $user->getId());
        } else {
            $existe = $entityManager->getRepository(Repertoire::class)->verifierNomDejaExistantGroupe($nouveauNom, $parent, $repertoire->getGroupeRepertoire());
        }

        if (empty($nouveauNom) || trim($nouveauNom) === '') {
            return $this->json([
                'success' => false,
                'message' => 'Le nom du répertoire ne peut pas être vide'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($existe != null){
            return $this->json([
                'success' => false,
                'message' => 'Un répertoire avec ce nom existe déjà à cet emplacement'
            ], Response::HTTP_CONFLICT);
        }

        $ancienNom = $repertoire->getName();
        $repertoire->setName(trim($nouveauNom));

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Répertoire renommé avec succès',
            'data' => [
                'id' => $repertoire->getId(),
                'oldName' => $ancienNom,
                'newName' => $repertoire->getName(),
                'fullPath' => $repertoire->getFullPath()
            ]
        ], Response::HTTP_OK);

    }

    #[IsGranted('ROLE_USER')]
    #[Route('/repertoire/corbeille', name: 'repertoire_corbeille')]
    public function corbeille(RepertoireRepository $repo): Response
    {
        $user = $this->getUser();
        $repertoires = $repo->findDeletedByUser($user);

        return $this->render('yaml_file/corbeilleRepertoire.html.twig', [
            'repertoires' => $repertoires,
        ]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/repertoire/supprimerCorbeille/{id}', name: 'deleteRepertoire', options: ["expose" => true], methods: ['DELETE'])]
    public function supprimerRepertoire(
        ?Repertoire $repertoire,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse
    {

        $utilisateur = $this->getUser();

        if (!$repertoire) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        if ($repertoire->getUtilisateurRepertoire() !== $utilisateur) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $submittedToken = $request->getPayload()->get('_token');
        if (!$this->isCsrfTokenValid('delete_repertoire' . $repertoire->getId(), $submittedToken)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $repertoire->softDelete();
        $em->persist($repertoire);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }


    #[IsGranted('ROLE_USER')]
    #[Route('/repertoire/restaurer/{id}', name: 'restore_repertoire')]
    public function restore(Repertoire $repertoire, EntityManagerInterface $em): Response
    {
        if (!$repertoire) {
            $this->addFlash("error", "Le répertoire n'existe pas");
            $this->redirectToRoute('repertoire');
        }

        if ($repertoire->getUtilisateurRepertoire() !== $this->getUser()) {
            $this->addFlash("error", "Vous ne pouvez pas restaurer ce répertoire");
        }

        $repertoire->restore();
        $em->flush();

        $this->addFlash("success", "Le répertoire à bien été restauré");
        return $this->redirectToRoute('repertoire_corbeille');
    }


    #[IsGranted('ROLE_USER')]
    #[Route('/repertoire/supprimer/{id}', name: 'delete_repertoire_permanent')]
    public function supprime(Repertoire $repertoire, EntityManagerInterface $em): Response
    {
        if (!$repertoire) {
            $this->addFlash("error", "Le répertoire n'existe pas");
            $this->redirectToRoute('repertoire');
        }

        if ($repertoire->getUtilisateurRepertoire() !== $this->getUser()) {
            $this->addFlash("error", "Vous ne pouvez pas restaurer ce répertoire");
        }

        $this->deleteRepertoireWithFiles($repertoire, $em);
        $em->flush();

        $this->addFlash("success", "Le répertoire à bien été supprimé");
        return $this->redirectToRoute('repertoire_corbeille');
    }


    #[IsGranted('ROLE_USER')]
    #[Route('/repertoire/corbeille/delete-all', name: 'delete_repertoire_permanent_all')]
    public function deleteAll(
        EntityManagerInterface $em,
        RepertoireRepository $repo
    ): Response {
        $user = $this->getUser();
        $repertoires = $repo->findDeletedByUser($user);

        foreach ($repertoires as $rep) {
            $this->deleteRepertoireWithFiles($rep, $em);
        }

        $em->flush();

        $this->addFlash('success', 'Tous les répertoires supprimés définitivement !');

        return $this->redirectToRoute('repertoire_corbeille');
    }


    private function deleteRepertoireWithFiles(Repertoire $repertoire, EntityManagerInterface $em): void
    {
        foreach ($repertoire->getAccesYamlFilesUtilisateur() as $uyr) {
            $em->remove($uyr->getYamlFile());
            $em->remove($uyr);
        }

        foreach ($repertoire->getChildren() as $child) {
            $this->deleteRepertoireWithFiles($child, $em);
        }

        $em->remove($repertoire);
    }

}
