<?php

namespace App\Controller;

use App\Entity\YamlFileBiblio;
use App\Form\YamlFileBiblioType;
use App\Form\YamlFileType;
use App\Service\FlashMessageHelperInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class YamlFileBiblioController extends AbstractController
{

    #[Route('/bibliotheque', name: 'bibliotheque')]
    public function bibliotheque(): Response
    {
        $utilisateur = $this->getUser();

        if (!$utilisateur) {
            $this->addFlash("error", "Vous devez être connecté pour accéder à la bibliothèque");
            return $this->redirectToRoute('connexion');
        }

        return $this->render('yaml_file_biblio/bibliotheque.html.twig');
    }

    #[IsGranted("ROLE_PROF")]
    #[Route('/bibliotheque/upload', name: 'biblio_upload', methods: ['GET', 'POST'])]
    public function upload(
        Request $request,
        EntityManagerInterface $entityManager,
        FlashMessageHelperInterface $flashMessageHelperInterface,
    ): Response {

        $yamlFile = new YamlFileBiblio();

        $form = $this->createForm(YamlFileBiblioType::class, $yamlFile, [
            'method' => 'POST',
            'action' => $this->generateUrl('biblio_upload'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $uploadedFile = $form->get('yamlFile')->getData();

            if (!$uploadedFile) {
                $this->addFlash('error', 'Aucun fichier reçu.');
                return $this->redirectToRoute('biblio_upload');
            }

            $extension = strtolower($uploadedFile->getClientOriginalExtension());
            if (!in_array($extension, ['yaml', 'yml'])) {
                $this->addFlash('error', 'Seuls les fichiers .yaml ou .yml sont autorisés.');
                return $this->redirectToRoute('yaml_upload');
            }

            $nameFile = $uploadedFile->getClientOriginalName();


            // Vérifier si un fichier avec le même nom existe déjà
            $results = $entityManager
                ->getRepository(YamlFileBiblio::class)
                ->findByNom($nameFile);

            if (count($results) > 0) {
                $this->addFlash('error', sprintf(
                    'Un fichier nommé "%s" existe déjà.',
                    $nameFile
                ));
                return $this->redirectToRoute('biblio_upload');
            }

            try {
                $content = file_get_contents($uploadedFile->getRealPath());

                if (trim($content) === '') {
                    $this->addFlash('error', 'Le fichier YAML ne peut pas être vide');
                    return $this->redirectToRoute('yaml_upload');
                }

                // Le répertoire a déjà été défini par le formulaire via setRepertoire()
                $yamlFile->setNameFile($nameFile);
                $yamlFile->setBodyFile($content);

                $entityManager->persist($yamlFile);
                $entityManager->flush();

                $this->addFlash('success', sprintf(
                    'Fichier YAML "%s" importé avec succès.',
                    $nameFile,
                ));

                return $this->redirectToRoute('bibliotheque');
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de la lecture du fichier: ' . $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue: ' . $e->getMessage());
            }
        }
        $flashMessageHelperInterface->addFormErrorsAsFlash($form);

        return $this->render('yaml_file_biblio/uploadBiblio.html.twig', [
            'formulaireYaml' => $form
        ]);
    }
}
