<?php

namespace App\Controller;

use App\Entity\EtrePartage;
use App\Form\EtrePartageType;
use App\Repository\EtrePartageRepository;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EtrePartageController extends AbstractController
{
    #[IsGranted("ROLE_USER")]
    #[Route('/partager_fichier', name: 'partager_yaml', methods: ['GET', 'POST'])]
    public function partagerYamlFile(
        Request $request,
        EntityManagerInterface $entityManager,
        EtrePartageRepository $etrePartageRepo
    ): Response {
        $utilisateur = $this->getUser();
        $form = $this->createForm(EtrePartageType::class, null, [
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'utilisateur' => $utilisateur
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $partage = $form->getData();
            $cible = $partage->getUtilisateur();
            $fichier = $partage->getYamlFile();

            try {
                $partage->assertNotSelfShare($this->getUser());
                $partage->assertNotDuplicate(
                    $etrePartageRepo->existsPartage($cible, $fichier)
                );

            } catch (DomainException $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('partager_yaml');
            }

            $partage->setDatePartage(new \DateTimeImmutable());

            $entityManager->persist($partage);
            $entityManager->flush();

            $this->addFlash('success', 'Fichier partagé avec succès.');
            return $this->redirectToRoute('partager_yaml');
        }


        return $this->render('etre_partage/partager_yaml.html.twig', [
            'formulairePartage' => $form->createView(),
        ]);
    }
}