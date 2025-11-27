<?php

namespace App\Repository;

use App\Entity\Repertoire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<Repertoire>
 */
class RepertoireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Repertoire::class);
    }

    public function recupererRepertoireUtilisateur(UserInterface $utilisateur)
    {
        return $this->findBy([
            'utilisateur_repertoire' => $utilisateur
        ]);

    }

    public function recupererRepertoireGroupe($idGroupe)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.groupe_repertoire = :idgroupe')
            ->setParameter('idgroupe', $idGroupe)
            ->getQuery()
            ->getResult();
    }

    public function recupererRepertoireRacineUtilisateur($idUtilisateur)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.utilisateur_repertoire = :idutilisateur')
            ->andWhere('r.parent IS NULL')
            ->setParameter('idutilisateur', $idUtilisateur)
            ->getQuery()
            ->getOneOrNullResult();

    }

    public function recupererRepertoireRacineGroupe($idGroupe)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.groupe_repertoire = :idgroupe')
            ->andWhere('r.parent IS NULL')
            ->setParameter('idgroupe', $idGroupe)
            ->getQuery()
            ->getOneOrNullResult();

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
