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

    public function findByYamlFileAndGroupe(int $yamlFileId, int $groupeId)
    {
        return $this->createQueryBuilder('yg')
            ->join('yg.yamlFile', 'yf')
            ->join('yg.groupe', 'g')
            ->andWhere('yf.id = :yamlFileId')
            ->andWhere('g.id = :groupeId')
            ->setParameter('yamlFileId', $yamlFileId)
            ->setParameter('groupeId', $groupeId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsYamlFileGroupe($idGroupe, $nameFile, $idRepertoire): bool
    {
        return (bool) $this->createQueryBuilder('u')
            ->select('COUNT(yf)')
            ->join('u.yamlFile', 'yf')
            ->andWhere('u.groupe = :idGroupe')
            ->andWhere('u.repertoire = :idRepertoire')
            ->andWhere('yf.nameFile = :nameFile')
            ->setParameter('idGroupe', $idGroupe)
            ->setParameter('idRepertoire', $idRepertoire)
            ->setParameter('nameFile', $nameFile)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function recuperertoutYamlfileGroupeParRepertoire($idGroupe)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.groupe = :idGroupe')
            ->setParameter('idGroupe', $idGroupe)
            ->OrderBy('u.repertoire', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function supprimerYamlfileGroupeParRepertoire($idYamlfile)
    {
        $qb = $this->createQueryBuilder('y')
            ->delete()
            ->where('y.yamlFile = :id')
            ->setParameter('id', $idYamlfile)
            ->getQuery();

        return $qb->execute();
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
