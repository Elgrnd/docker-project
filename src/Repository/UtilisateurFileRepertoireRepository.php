<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use App\Entity\UtilisateurFileRepertoire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UtilisateurFileRepertoire>
 */
class UtilisateurFileRepertoireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UtilisateurFileRepertoire::class);
    }

    public function recuperertoutFileUtilisateurParRepertoire($idUtilisateur)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.utilisateur = :idUtilisateur')
            ->setParameter('idUtilisateur', $idUtilisateur)
            ->OrderBy('u.repertoire', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function recupererunFileUtilisateurParRepertoire($idUtilisateur, $idFile)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.utilisateur = :idUtilisateur')
            ->andWhere('u.file = :idFile')
            ->setParameter('idUtilisateur', $idUtilisateur)
            ->setParameter('idFile', $idFile)
            ->OrderBy('u.repertoire', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findFilesForUserAdmin(Utilisateur $utilisateur, bool $isAdmin): array
    {
        $qb = $this->createQueryBuilder('uyfr')
            ->select('uyfr', 'yf', 'r')
            ->join('uyfr.file', 'yf')
            ->join('uyfr.repertoire', 'r')
            ->andWhere('yf.deletedAt IS NULL')
            ->addOrderBy('uyfr.utilisateur', 'ASC')
            ->addOrderBy('yf.nameFile', 'ASC');

        if (!$isAdmin) {
            $qb->andWhere('uyfr.utilisateur = :user')
                ->setParameter('user', $utilisateur);
        }

        return $qb->getQuery()->getResult();
    }


    public function findFilesForUser(Utilisateur $utilisateur): array
    {
        $qb = $this->createQueryBuilder('uyfr')
            ->select('uyfr', 'yf', 'r')
            ->join('uyfr.file', 'yf')
            ->join('uyfr.repertoire', 'r')
            ->andWhere('uyfr.utilisateur = :user')
            ->setParameter('user', $utilisateur)
            ->addOrderBy('yf.nameFile', 'ASC');

        return $qb->getQuery()->getResult();
    }

    public function supprimerFileUtilisateurParRepertoire($idFile)
    {
        $qb = $this->createQueryBuilder('y')
            ->delete()
            ->where('y.file = :id')
            ->setParameter('id', $idFile)
            ->getQuery();

        return $qb->execute();
    }

    public function existsFileUtilisateur($idUtilisateur, $nameFile, $idRepertoire): bool
    {
        return (bool) $this->createQueryBuilder('u')
            ->select('COUNT(yf)')
            ->join('u.file', 'yf')
            ->andWhere('u.utilisateur = :idUtilisateur')
            ->andWhere('u.repertoire = :idRepertoire')
            ->andWhere('yf.nameFile = :nameFile')
            ->setParameter('idUtilisateur', $idUtilisateur)
            ->setParameter('idRepertoire', $idRepertoire)
            ->setParameter('nameFile', $nameFile)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
