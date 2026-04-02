<?php

namespace App\Controller;

use App\Entity\EtrePartage;
use App\Entity\File;
use App\Entity\Utilisateur;
use App\Form\EtrePartageType;
use App\Form\PartagerFichierType;
use App\Repository\EtrePartageRepository;
use App\Repository\UtilisateurFileRepertoireRepository;
use App\Repository\UtilisateurRepository;
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
    #[Route('/partages', name: 'partages', methods: ['GET', 'POST'])]
    public function partages(
        Request $request,
        EntityManagerInterface $entityManager,
        EtrePartageRepository $etrePartageRepo,
        ManagerRegistry $doctrine
    ): Response {
        $utilisateur = $this->getUser();

        $partages = $etrePartageRepo->findByOwner($utilisateur);

        $partage = new EtrePartage();

        $form = $this->createForm(EtrePartageType::class, $partage, [
            'utilisateur' => $utilisateur,
            'is_Admin' => $this->isGranted('ROLE_ADMIN'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $fileId = $form->get('fichier')->getData();

            $file = $doctrine->getRepository(File::class)->find($fileId);
            if (!$file) {
                $this->addFlash('error', 'Fichier introuvable.');
                return $this->redirectToRoute('partages');
            }

            $partage->setFile($file);

            try {
                $partage->assertNotSelfShare($utilisateur);
                $partage->assertNotDuplicate(
                    $etrePartageRepo->existsPartage(
                        $partage->getUtilisateur(),
                        $file
                    )
                );
            } catch (DomainException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('partages');
            }

            $partage->setDatePartage(new \DateTimeImmutable());

            $entityManager->persist($partage);
            $entityManager->flush();

            $this->addFlash('success', 'Fichier partagé avec succès.');
            return $this->redirectToRoute('partages');
        }

        return $this->render('etre_partage/partages.html.twig', [
            'partages' => $partages,
            'formulairePartage' => $form->createView(),
        ]);
    }

    #[Route('/partage/{id}/delete', name: 'delete_partage')]
    public function delete(EtrePartage $partage, EntityManagerInterface $em): Response
    {
        $em->remove($partage);
        $em->flush();

        return $this->redirectToRoute('partages');
    }

    #[Route('/partage/{id}/edit', name: 'edit_droit')]
    public function edit(
        Request $request,
        EtrePartage $partage,
        EntityManagerInterface $em
    ): Response
    {
        if ($request->isMethod('POST')) {
            $droit = $request->request->get('droit');
            $partage->setDroit($droit);
            $em->flush();
        }
        return $this->redirectToRoute('partages');
    }

    #[Route('/file/{id}/partager', name: 'partager_fichier', methods: ['POST'], options: ['expose' => true])]
    public function partager_fichier(
        File $file,
        Request $request,
        EntityManagerInterface $em,
        EtrePartageRepository $repo
    ): Response {
        $user = $this->getUser();

        $partage = new EtrePartage();
        $partage->setFile($file);

        $form = $this->createForm(PartagerFichierType::class, $partage, [
            'utilisateur' => $user
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $partage->assertNotSelfShare($user);
                    $partage->assertNotDuplicate(
                        $repo->existsPartage(
                            $partage->getUtilisateur(),
                            $file
                        )
                    );

                    $partage->setDatePartage(new \DateTimeImmutable());
                    $em->persist($partage);
                    $em->flush();

                    $this->addFlash('success', 'Fichier partagé avec succès.');
                } catch (DomainException $e) {
                    $this->addFlash('error', $e->getMessage());
                }
            } else {
                $this->addFlash('error', 'Le formulaire contient des erreurs, veuillez vérifier les champs.');
            }
        }
        return $this->redirectToRoute('repertoire');
    }
}
