<?php

namespace App\Repository;

use App\Entity\GroupeYamlFileRepertoire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupeYamlFileRepertoire>
 */
class GroupeYamlFileRepertoireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupeYamlFileRepertoire::class);
    }

    public function recupererYamlFileDepuisGroupe($idYamlFile)
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.yamlFile = :yamlFile')
            ->setParameter('yamlFile', $idYamlFile)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return GroupeYamlFileRepertoire[] Returns an array of GroupeYamlFileRepertoire objects
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

    //    public function findOneBySomeField($value): ?GroupeYamlFileRepertoire
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
