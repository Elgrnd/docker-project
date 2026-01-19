<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use App\Entity\UtilisateurYamlFileRepertoire;
use App\Entity\YamlFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UtilisateurYamlFileRepertoire>
 */
class UtilisateurYamlFileRepertoireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UtilisateurYamlFileRepertoire::class);
    }

    public function recuperertoutYamlfileUtilisateurParRepertoire($idUtilisateur)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.utilisateur = :idUtilisateur')
            ->setParameter('idUtilisateur', $idUtilisateur)
            ->OrderBy('u.repertoire', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function recupererunYamlfileUtilisateurParRepertoire($idUtilisateur, $idYamlfile)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.utilisateur = :idUtilisateur')
            ->andWhere('u.yamlFile = :idYaml')
            ->setParameter('idUtilisateur', $idUtilisateur)
            ->setParameter('idYaml', $idYamlfile)
            ->OrderBy('u.repertoire', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findYamlFilesForUserAdmin(Utilisateur $utilisateur, bool $isAdmin): array
    {
        $qb = $this->createQueryBuilder('uyfr')
            ->select('uyfr', 'yf', 'r')
            ->join('uyfr.yamlFile', 'yf')
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

    public function findYamlFilesForUser(Utilisateur $utilisateur): array
    {
        $qb = $this->createQueryBuilder('uyfr')
            ->select('uyfr', 'yf', 'r')
            ->join('uyfr.yamlFile', 'yf')
            ->join('uyfr.repertoire', 'r')
            ->andWhere('uyfr.utilisateur = :user')
            ->setParameter('user', $utilisateur)
            ->addOrderBy('yf.nameFile', 'ASC');

        return $qb->getQuery()->getResult();
    }



    public function supprimerYamlfileUtilisateurParRepertoire($idYamlfile)
    {
        $qb = $this->createQueryBuilder('y')
            ->delete()
            ->where('y.yamlFile = :id')
            ->setParameter('id', $idYamlfile)
            ->getQuery();

        return $qb->execute();
    }

    public function existsYamlFileUtilisateur($idUtilisateur, $nameFile, $idRepertoire): bool
    {
        return (bool) $this->createQueryBuilder('u')
            ->select('COUNT(yf)')
            ->join('u.yamlFile', 'yf')
            ->andWhere('u.utilisateur = :idUtilisateur')
            ->andWhere('u.repertoire = :idRepertoire')
            ->andWhere('yf.nameFile = :nameFile')
            ->setParameter('idUtilisateur', $idUtilisateur)
            ->setParameter('idRepertoire', $idRepertoire)
            ->setParameter('nameFile', $nameFile)
            ->getQuery()
            ->getSingleScalarResult();
    }

    //    /**
    //     * @return UtilisateurYamlFileRepertoire[] Returns an array of UtilisateurYamlFileRepertoire objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?UtilisateurYamlFileRepertoire
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
