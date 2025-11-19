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

    public function recupererunYamlfileUtilisateurParRepertoire($idUtilisateur, $idYaml)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.utilisateur = :idUtilisateur')
            ->andWhere('u.yaml_file = :idYaml')
            ->setParameter('idUtilisateur', $idUtilisateur)
            ->setParameter('idYaml', $idYaml)
            ->OrderBy('u.repertoire', 'ASC')
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
