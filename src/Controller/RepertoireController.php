<?php

namespace App\Controller;

use App\Entity\Repertoire;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;


final class RepertoireController extends AbstractController
{
    #[Route('/repertoire/rename/{id}', name: 'rename_repertoire', methods: ['POST'], options: ["expose" => true])]
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
}
