<?php

namespace App\Repository;

use App\Entity\EtrePartage;
use App\Entity\File;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EtrePartage>
 */
class EtrePartageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EtrePartage::class);
    }

    public function existsPartage(Utilisateur $utilisateur, File $file): bool
    {
        return (bool) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.utilisateur = :utilisateur')
            ->andWhere('e.file = :file')
            ->setParameter('utilisateur', $utilisateur)
            ->setParameter('file', $file)
            ->getQuery()
            ->getSingleScalarResult();
    }


    //    /**
    //     * @return EtrePartage[] Returns an array of EtrePartage objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?EtrePartage
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
