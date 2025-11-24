<?php

namespace App\Controller;

use App\Entity\EtrePartage;
use App\Form\EtrePartageType;
use App\Repository\EtrePartageRepository;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EtrePartageController extends AbstractController
{
    #[Route('/partager_fichier', name: 'partager_yaml', methods: ['GET', 'POST'])]
    public function partagerYaml(
        Request $request,
        EntityManagerInterface $entityManager,
        EtrePartageRepository $etrePartageRepo
    ): Response {
        $utilisateur = $this->getUser();
        if (!$utilisateur) {
            $this->addFlash('error', 'Veuillez vous connecter.');
            return $this->redirectToRoute('connexion');
        }

        $form = $this->createForm(EtrePartageType::class, null, [
            'is_admin' => $this->isGranted('ROLE_ADMIN'),
            'utilisateur' => $utilisateur
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $partage = $form->getData();
            $utilisateurCourant = $this->getUser();
            $cible = $partage->getUtilisateur();
            $fichier = $partage->getYamlFile();

            if ($partage->getUtilisateur() === $utilisateurCourant) {
                $this->addFlash('error', 'Vous ne pouvez pas partager un fichier avec vous-même.');
                return $this->redirectToRoute('partager_yaml');
            }

            if ($etrePartageRepo->existsPartage($cible, $fichier)) {
                $this->addFlash('error', 'Ce fichier a déjà été partagé avec cet utilisateur.');
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