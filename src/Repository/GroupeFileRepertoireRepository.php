<?php

namespace App\Repository;

use App\Entity\GroupeFileRepertoire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GroupeFileRepertoire>
 */
class GroupeFileRepertoireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GroupeFileRepertoire::class);
    }

    public function findByFileAndGroupe(int $fileId, int $groupeId)
    {
        return $this->createQueryBuilder('yg')
            ->join('yg.file', 'yf')
            ->join('yg.groupe', 'g')
            ->andWhere('yf.id = :fileId')
            ->andWhere('g.id = :groupeId')
            ->setParameter('fileId', $fileId)
            ->setParameter('groupeId', $groupeId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsFileGroupe(int $idGroupe, string $nameFile, int $idRepertoire): bool
    {
        $count = (int) $this->createQueryBuilder('u')
            ->select('COUNT(f)')
            ->join('u.file', 'f')
            ->andWhere('u.groupe = :idGroupe')
            ->andWhere('u.repertoire = :idRepertoire')
            ->andWhere('f.nameFile = :nameFile')
            ->setParameter('idGroupe', $idGroupe)
            ->setParameter('idRepertoire', $idRepertoire)
            ->setParameter('nameFile', $nameFile)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }



    public function recuperertoutFileGroupeParRepertoire($idGroupe)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.groupe = :idGroupe')
            ->setParameter('idGroupe', $idGroupe)
            ->OrderBy('u.repertoire', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function supprimerFileDansUnGroupe(int $idFile, int $idGroupe): int
    {
        return $this->createQueryBuilder('gfr')
            ->delete()
            ->where('gfr.file = :fileId')
            ->andWhere('gfr.groupe = :groupeId')
            ->setParameter('fileId', $idFile)
            ->setParameter('groupeId', $idGroupe)
            ->getQuery()
            ->execute();
    }


    //    /**
    //     * @return GroupeFileRepertoire[] Returns an array of GroupeFileRepertoire objects
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

    //    public function findOneBySomeField($value): ?GroupeFileRepertoire
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
