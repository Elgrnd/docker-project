<?php

namespace App\Repository;

use App\Entity\UtilisateurYamlFileRepertoire;
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

    public function supprimerYamlfileUtilisateurParRepertoire($idYamlfile)
    {
        $qb = $this->createQueryBuilder('y')
            ->delete()
            ->where('y.yamlFile = :id')
            ->setParameter('id', $idYamlfile)
            ->getQuery();

        return $qb->execute();
    }

    public function verifierSiYamlFileExiste($idUtilisateur, $nameFile, $idRepertoire){
        return $this->createQueryBuilder('u')
            ->join('u.yamlFile', 'yf')
            ->andWhere('u.utilisateur = :idUtilisateur')
            ->andWhere('u.repertoire = :idRepertoire')
            ->andWhere('yf.nameFile = :nameFile')
            ->setParameter('idUtilisateur', $idUtilisateur)
            ->setParameter('idRepertoire', $idRepertoire)
            ->setParameter('nameFile', $nameFile)
            ->getQuery()
            ->getResult();
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
