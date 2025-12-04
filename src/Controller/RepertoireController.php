<?php

namespace App\Controller;

use App\Entity\Repertoire;
use App\Repository\RepertoireRepository;
use App\Service\RepertoireService;
use App\Repository\YamlFileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use ZipArchive;


final class RepertoireController extends AbstractController
{
    #[IsGranted('REP_EDIT', subject: 'repertoire')]
    #[Route('/repertoire/rename/{id}', name: 'rename_repertoire', options: ["expose" => true], methods: ['POST'])]
    public function index(EntityManagerInterface $entityManager, Repertoire $repertoire, Request $request): Response
    {
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $nouveauNom = $data['name'] ?? null;



        $parent = $repertoire->getParent();
        if ($repertoire->getUtilisateurRepertoire() !== null){
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
    public function corbeille(RepertoireRepository $repertoireRepository, YamlFileRepository $yamlFileRepository): Response
    {
        $user = $this->getUser();
        $repertoires = $repertoireRepository->findDeletedByUser($user);
        $yamlFiles   = $yamlFileRepository->findDeletedByUser($user);

        return $this->render('yaml_file/corbeilleRepertoire.html.twig', [
            'repertoires' => $repertoires,
            'yamlFiles'   => $yamlFiles,
        ]);
    }

    #[IsGranted('REP_EDIT', subject: 'repertoire')]
    #[Route('/repertoire/supprimerCorbeille/{id}', name: 'deleteRepertoire', options: ["expose" => true], methods: ['DELETE'])]
    public function supprimerRepertoire(
        ?Repertoire $repertoire,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse
    {

        $utilisateur = $this->getUser();

        $submittedToken = $request->getPayload()->get('_token');
        if (!$this->isCsrfTokenValid('delete_repertoire' . $repertoire->getId(), $submittedToken)) {
            return new JsonResponse(null, Response::HTTP_FORBIDDEN);
        }

        $repertoire->softDelete();
        $em->persist($repertoire);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }


    #[IsGranted('REP_EDIT', subject: 'repertoire')]
    #[Route('/repertoire/restaurer/{id}', name: 'restore_repertoire')]
    public function restore(Repertoire $repertoire, EntityManagerInterface $em): Response
    {
        $repertoire->restore();
        $em->flush();

        $this->addFlash("success", "Le répertoire à bien été restauré");
        return $this->redirectToRoute('repertoire_corbeille');
    }


    #[IsGranted('REP_EDIT', subject: 'repertoire')]
    #[Route('/repertoire/supprimer/{id}', name: 'delete_repertoire_permanent')]
    public function supprime(Repertoire $repertoire, EntityManagerInterface $em, RepertoireService $repertoireService): Response
    {
        $repertoireService->deleteRepertoireWithFiles($repertoire, $em);
        $em->flush();

        $this->addFlash("success", "Le répertoire à bien été supprimé");
        return $this->redirectToRoute('repertoire_corbeille');
    }


    #[IsGranted('ROLE_USER')]
    #[Route('/repertoire/corbeille/delete-all', name: 'delete_repertoire_permanent_all')]
    public function deleteAll(
        EntityManagerInterface $em,
        RepertoireRepository $repo,
        RepertoireService $repertoireService
    ): Response {
        $user = $this->getUser();
        $repertoires = $repo->findDeletedByUser($user);

        foreach ($repertoires as $rep) {
            $repertoireService->deleteRepertoireWithFiles($rep, $em);
        }

        $em->flush();

        $this->addFlash('success', 'Tous les répertoires supprimés définitivement !');

        return $this->redirectToRoute('repertoire_corbeille');
    }

    /**
     * @throws Exception
     */
    #[IsGranted("REP_UPLOAD", subject: 'repertoire')]
    #[Route('/repertoire/telecharger/{id}', name: 'repertoire_telecharger_zip')]
    public function telechargerZip(
        Repertoire $repertoire,
        RepertoireService $repertoireService
    ): Response {
        $zipPath = sys_get_temp_dir() . '/repertoire_' . $repertoire->getId() . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Impossible de créer l’archive ZIP.");
        }

        $repertoireService->addRepertoireToZip($repertoire, $zip, '');

        $zip->close();

        return $this->file($zipPath, $repertoire->getName() . '.zip')
            ->deleteFileAfterSend();
    }
}
