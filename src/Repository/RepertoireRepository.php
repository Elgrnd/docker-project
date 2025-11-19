<?php

namespace App\Repository;

use App\Entity\Repertoire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Repertoire>
 */
class RepertoireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Repertoire::class);
    }

    public function repertoireUtilisateur($idUtilisateur)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.utilisateur_repertoire = :idutilisteur')
            ->setParameter('idutilisteur', $idUtilisateur)
            ->getQuery()
            ->getResult();
    }

    public function repertoireGroupe($idGroupe)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.utilisateur_repertoire = :idgroupe')
            ->setParameter('idgroupe', $idGroupe)
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Repertoire[] Returns an array of Repertoire objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Repertoire
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
