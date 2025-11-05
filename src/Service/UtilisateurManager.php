<?php

namespace App\Service;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UtilisateurManager implements UtilisateurManagerInterface
{

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private UtilisateurRepository $utilisateurRepository
    ){}

    /**
     * Chiffre le mot de passe puis l'affecte au champ correspondant dans la classe de l'utilisateur
     */
    private function chiffrerMotDePasse(Utilisateur $utilisateur, ?string $plainPassword) : void {
        $hashed = $this->passwordHasher->hashPassword($utilisateur, $plainPassword);
        $utilisateur->setPassword($hashed);
    }

    /**
     * Réalise toutes les opérations nécessaires avant l'enregistrement en base d'un nouvel utilisateur, après soumissions du formulaire (hachage du mot de passe, sauvegarde de la photo de profil...)
     */
    public function processNewUtilisateur(Utilisateur $utilisateur, ?string $plainPassword) : void {
        $this->chiffrerMotDePasse($utilisateur, $plainPassword);
    }

    public function getUtilisateursAvecVm() : array {
        return $this->utilisateurRepository->findBy(["proxmoxVmid" => ['not' => null]]);
    }

}