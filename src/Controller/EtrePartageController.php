<?php

namespace App\Controller;

use App\Entity\EtrePartage;
use App\Form\EtrePartageType;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EtrePartageController extends AbstractController
{
    #[Route('/partager', name: 'partagerYamlFileAdmin', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager, FlashMessageHelperInterface $flashMessageHelperInterface): Response
    {
        $utilisateur = $this->getUser();

        if ($utilisateur === null) {
            $this->addFlash('error', 'Vous devez être connecté pour partager un fichier');
            return $this->redirectToRoute('connexion');
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Vous devez être administrateur pour partager ces fichiers');
            return $this->redirectToRoute('index');
        }

        $etrePartage = new EtrePartage();

        $form = $this->createForm(EtrePartageType::class, $etrePartage, [
            'method' => 'POST',
            'action' => $this->generateUrl('partagerYamlFileAdmin'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $yamlFile = $etrePartage->getYamlFile();
            $cible = $etrePartage->getUtilisateur();

            if ($cible === $utilisateur) {
                $this->addFlash('error', 'Vous ne pouvez pas vous partager un fichier à vous-même.');
                return $this->redirectToRoute('partagerYamlFileAdmin');
            }

            $repository = $entityManager->getRepository(EtrePartage::class);
            if ($repository->existeDeja($cible, $yamlFile)) {
                $this->addFlash('error', 'Ce fichier a déjà été partagé avec cet utilisateur.');
                return $this->redirectToRoute('partagerYamlFileAdmin');
            }

            $etrePartage->setDatePartage(new \DateTimeImmutable());

            $entityManager->persist($etrePartage);
            $entityManager->flush();

            $this->addFlash('success', 'Le fichier a été partagé avec succès !');
            return $this->redirectToRoute('partagerYamlFileAdmin');
        }

        $flashMessageHelperInterface->addFormErrorsAsFlash($form);

        return $this->render('etre_partage/partagerYamlFileAdmin.html.twig', [
            'formulairePartage' => $form,
        ]);
    }
}
