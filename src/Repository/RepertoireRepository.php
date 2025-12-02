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

    public function recupererRepertoireUtilisateurActifs(UserInterface $utilisateur)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.utilisateur_repertoire = :user')
            ->andWhere('r.deletedAt IS NULL')
            ->setParameter('user', $utilisateur)
            ->getQuery()
            ->getResult();
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
            ->andWhere('r.deletedAt IS NULL')
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

    public function verifierNomDejaExistantUtilsateur($name, $parent, $idUtilisateur)
    {
        if ($parent == null) {
            return $this->createQueryBuilder('r')
                ->andWhere('r.parent IS NULL')
                ->andWhere('r.name = :nom')
                ->andWhere('r.utilisateur_repertoire = :idutilisateur')
                ->setParameter('nom', $name)
                ->setParameter('idutilisateur', $idUtilisateur)
                ->setParameter('parent', $parent)
                ->getQuery()
                ->getOneOrNullResult();
        }
        return $this->createQueryBuilder('r')
            ->andWhere('r.parent = :parent')
            ->andWhere('r.name = :nom')
            ->andWhere('r.utilisateur_repertoire = :idutilisateur')
            ->setParameter('nom', $name)
            ->setParameter('idutilisateur', $idUtilisateur)
            ->setParameter('parent', $parent)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function verifierNomDejaExistantGroupe($name, $parent, $idgroupe)
    {
        if ($parent == null) {
            return $this->createQueryBuilder('r')
                ->andWhere('r.parent IS NULL')
                ->andWhere('r.name = :nom')
                ->andWhere('r.groupe_repertoire = :idgroupe')
                ->setParameter('nom', $name)
                ->setParameter('idgroupe', $idgroupe)
                ->setParameter('parent', $parent)
                ->getQuery()
                ->getOneOrNullResult();
        }
        return $this->createQueryBuilder('r')
            ->andWhere('r.parent = :parent')
            ->andWhere('r.name = :nom')
            ->andWhere('r.groupe_repertoire = :idgroupe')
            ->setParameter('nom', $name)
            ->setParameter('idgroupe', $idgroupe)
            ->setParameter('parent', $parent)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findDeletedByUser(?UserInterface $user)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.utilisateur_repertoire = :idutilisateur')
            ->andWhere('r.deletedAt IS NOT NULL')
            ->setParameter('idutilisateur', $user->getId())
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
