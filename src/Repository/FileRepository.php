<?php

namespace App\Repository;

use App\Entity\File;
use App\Entity\Groupe;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

class FileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, File::class);
    }

    /**
     * Retourne tous les fichiers supprimés (TextFile + BinaryFile) d'un utilisateur
     */
    public function findDeletedByUser(UserInterface $user): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.deletedAt IS NOT NULL')
            ->andWhere('f.utilisateur_file = :user')
            ->setParameter('user', $user)
            ->orderBy('f.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne tous les fichiers supprimés (TextFile + BinaryFile) dans un groupe
     * (suppression "par groupe" = deletedAt sur le pivot GroupeFileRepertoire)
     */
    public function findDeletedByGroupe(Groupe $groupe): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.groupeParRepertoire', 'gfr')
            ->where('gfr.deletedAt IS NOT NULL')
            ->andWhere('gfr.groupe = :groupe')
            ->setParameter('groupe', $groupe)
            ->orderBy('gfr.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findFromGitlabByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.utilisateur_file = :u')
            ->andWhere('f.fromGitlab = true')
            ->setParameter('u', $user)
            ->orderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
