<?php

namespace App\Controller;

use App\Entity\EtrePartage;
use App\Entity\File;
use App\Form\EtrePartageType;
use App\Repository\EtrePartageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EtrePartageController extends AbstractController
{
    #[IsGranted("ROLE_USER")]
    #[Route('/partager_fichier', name: 'partager_file', methods: ['GET', 'POST'])]
    public function partagerFile(
        Request $request,
        EntityManagerInterface $entityManager,
        EtrePartageRepository $etrePartageRepo,
        ManagerRegistry $doctrine
    ): Response {
        $utilisateur = $this->getUser();
        $partage = new EtrePartage();

        $form = $this->createForm(EtrePartageType::class, $partage, [
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'utilisateur' => $utilisateur,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fileId = $form->get('fileId')->getData();

            $file = $doctrine->getRepository(File::class)->find($fileId);
            if (!$file) {
                $this->addFlash('error', 'Fichier introuvable.');
                return $this->redirectToRoute('partager_file');
            }

            $partage->setFile($file);

            $cible = $partage->getUtilisateur();
            $fichier = $partage->getFile();

            try {
                $partage->assertNotSelfShare($utilisateur);
                $partage->assertNotDuplicate(
                    $etrePartageRepo->existsPartage($cible, $fichier)
                );
            } catch (DomainException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('partager_file');
            }

            $partage->setDatePartage(new \DateTimeImmutable());

            $entityManager->persist($partage);
            $entityManager->flush();

            $this->addFlash('success', 'Fichier partagé avec succès.');
            return $this->redirectToRoute('partager_file');
        }

        return $this->render('etre_partage/partagerFile.html.twig', [
            'formulairePartage' => $form->createView(),
        ]);
    }

}
