<?php

namespace App\Controller;

use App\Entity\Repertoire;
use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\ConfigurationLdap;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[Route('/panneauadmin/ldap')]
#[IsGranted('ROLE_ADMIN')]
class LdapController extends AbstractController
{
    public function __construct(
        private UtilisateurRepository $utilisateurRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    #[Route('/preview', name: 'admin_ldap_preview', methods: ['GET'])]
    public function preview(): Response
    {
        try {
            ConfigurationLDAP::connecterServeur();
            $utilisateursLdap = ConfigurationLDAP::getAll();
            ConfigurationLDAP::deconnecterServeur();

            $toCreate = [];
            $toUpdate = [];
            $skipped = [];
            $unchanged = [];

            foreach ($utilisateursLdap as $userLdap) {
                if (!$userLdap['login']) {
                    $skipped[] = [
                        'login' => $userLdap['login'] ?? 'N/A',
                        'reason' => 'Login manquant'
                    ];
                    continue;
                }

                $existingUser = $this->utilisateurRepository->findOneBy(['login' => $userLdap['login']]);

                if (!$existingUser) {
                    $toCreate[] = $userLdap;
                } else {
                    $changes = [];

                    // ✅ Normalisation des valeurs pour comparaison correcte
                    $existingNom = trim($existingUser->getNom() ?? '');
                    $ldapNom = trim($userLdap['nom'] ?? '');

                    $existingPrenom = trim($existingUser->getPrenom() ?? '');
                    $ldapPrenom = trim($userLdap['prenom'] ?? '');

                    $existingEmail = trim($existingUser->getAdresseMail() ?? '');
                    $ldapEmail = trim($userLdap['email'] ?? '');

                    $existingPromotion = trim($existingUser->getPromotion() ?? '');
                    $ldapPromotion = trim($userLdap['promotion'] ?? '');

                    // Comparaisons strictes
                    if ($existingNom !== $ldapNom) {
                        $changes[] = sprintf('Nom: "%s" → "%s"',
                            $existingNom ?: '(vide)',
                            $ldapNom ?: '(vide)'
                        );
                    }

                    if ($existingPrenom !== $ldapPrenom) {
                        $changes[] = sprintf('Prénom: "%s" → "%s"',
                            $existingPrenom ?: '(vide)',
                            $ldapPrenom ?: '(vide)'
                        );
                    }

                    if ($existingEmail !== $ldapEmail) {
                        $changes[] = sprintf('Email: "%s" → "%s"',
                            $existingEmail ?: '(vide)',
                            $ldapEmail ?: '(vide)'
                        );
                    }

                    if ($existingPromotion !== $ldapPromotion) {
                        $changes[] = sprintf('Promotion: "%s" → "%s"',
                            $existingPromotion ?: '(vide)',
                            $ldapPromotion ?: '(vide)'
                        );
                    }

                    if (!empty($changes)) {
                        $toUpdate[] = [
                            'user' => $userLdap,
                            'changes' => $changes
                        ];
                    } else {
                        $unchanged[] = $userLdap;
                    }
                }
            }

            return $this->render('ldap/preview.html.twig', [
                'toCreate' => $toCreate,
                'toUpdate' => $toUpdate,
                'skipped' => $skipped,
                'unchanged' => $unchanged,
                'totalLdap' => count($utilisateursLdap),
            ]);

        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur LDAP : ' . $e->getMessage());
            return $this->redirectToRoute('listeUtilisateurs');
        }
    }


    #[Route('/import', name: 'admin_ldap_import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('ldap-import', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('admin_ldap_preview');
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        try {
            ConfigurationLDAP::connecterServeur();
            $utilisateursLdap = ConfigurationLDAP::getAll();
            ConfigurationLDAP::deconnecterServeur();

            foreach ($utilisateursLdap as $userLdap) {
                try {
                    // Validation login
                    $login = $userLdap['login'] ?? null;

                    if (!$login || empty(trim($login))) {
                        $errors[] = "❌ Login manquant ou vide";
                        $skipped++;
                        continue;
                    }

                    $login = trim($login);
                    $this->logger->info("Traitement de: $login");

                    // ✅ Email optionnel (nullable)
                    $email = $userLdap['email'] ?? null;

                    if ($email) {
                        $email = trim($email);

                        // Valide uniquement si présent
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $errors[] = "⚠️ Email invalide pour '$login': '$email' → mis à null";
                            $email = null;
                        }
                    }

                    //Récupère ou crée l'utilisateur
                    $user = $this->utilisateurRepository->findOneBy(['login' => $login]);

                    if (!$user) {
                        $user = new Utilisateur();
                        $user->setLogin($login);

                        if (($userLdap['promotion'] ?? '') === "Personnel") {
                            $user->setRoles(['ROLE_PROFESSEUR']);
                        } else {
                            $user->setRoles(['ROLE_ETUDIANT']);
                        }

                        $imported++;
                        $this->logger->info("✅ Nouvel utilisateur créé: $login");
                    } else {
                        // Mise à jour
                        $updated++;
                    }

                    $user->setNom($userLdap['nom'] ?? '');
                    $user->setPrenom($userLdap['prenom'] ?? '');
                    $user->setAdresseMail($email);  // ✅ null accepté, doublons acceptés
                    $user->setPromotion($userLdap['promotion'] ?? '');
                    $user->setPassword(null);

                    $repertoire = new Repertoire();
                    $repertoire->setUtilisateurRepertoire($user);
                    $repertoire->setName('Répertoire personnel');

                    $this->entityManager->persist($user);
                    $this->entityManager->persist($repertoire);

                    // Flush par batch de 50
                    if (($imported + $updated) % 50 === 0) {
                        $this->entityManager->flush();
                        $this->entityManager->clear();
                        $this->logger->info("Batch flush: {$imported} importés, {$updated} mis à jour");
                    }

                } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                    $errorMsg = "❌ Doublon pour {$userLdap['login']}: " . $e->getMessage();
                    $this->logger->error($errorMsg);
                    $errors[] = $errorMsg;
                    $skipped++;

                } catch (\Exception $e) {
                    $errorMsg = sprintf(
                        '❌ Erreur pour %s: %s (ligne %d)',
                        $userLdap['login'] ?? 'inconnu',
                        $e->getMessage(),
                        $e->getLine()
                    );
                    $this->logger->error($errorMsg);
                    $errors[] = $errorMsg;
                    $skipped++;
                }
            }

            // ✅ Flush final
            try {
                $this->logger->info("Flush final...");
                $this->entityManager->flush();
                $this->logger->info("✅ Flush réussi");

            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                $this->addFlash('error', '❌ Contrainte unique violée: ' . $e->getMessage());
                $this->logger->error('Contrainte unique: ' . $e->getMessage());

                // Indique quelle contrainte
                if (strpos($e->getMessage(), 'login') !== false) {
                    $this->addFlash('error', '→ Doublon sur le login détecté');
                }

                return $this->redirectToRoute('admin_ldap_preview');

            } catch (\Exception $e) {
                $this->addFlash('error', '❌ Erreur flush: ' . $e->getMessage());
                $this->logger->error('Erreur flush: ' . $e->getMessage());
                return $this->redirectToRoute('admin_ldap_preview');
            }

            // ✅ Messages de succès
            if (empty($errors)) {
                $this->addFlash('success', sprintf(
                    '✅ Import réussi ! %d créé(s), %d mis à jour, %d ignoré(s)',
                    $imported,
                    $updated,
                    $skipped
                ));
            } else {
                $this->addFlash('warning', sprintf(
                    '⚠️ Import terminé avec avertissements : %d créé(s), %d mis à jour, %d ignoré(s)',
                    $imported,
                    $updated,
                    $skipped
                ));

                // Affiche max 10 erreurs
                foreach (array_slice($errors, 0, 10) as $error) {
                    $this->addFlash('info', $error);
                }

                if (count($errors) > 10) {
                    $this->addFlash('info', sprintf('... et %d autre(s) avertissement(s)', count($errors) - 10));
                }
            }

            return $this->redirectToRoute('listeUtilisateurs');

        } catch (\Exception $e) {
            ConfigurationLDAP::deconnecterServeur();
            $this->logger->error('Erreur fatale: ' . $e->getMessage());
            $this->addFlash('error', '❌ Erreur fatale : ' . $e->getMessage());
            return $this->redirectToRoute('admin_ldap_preview');
        }
    }

}
