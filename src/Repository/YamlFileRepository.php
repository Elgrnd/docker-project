<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use App\Entity\YamlFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<YamlFile>
 */
class YamlFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, YamlFile::class);
    }

    public function findByNomEtUtilisateur(string $nomFichier, UserInterface $utilisateur): array
    {
        return $this->findBy([
            'nameFile' => $nomFichier,
            'utilisateur' => $utilisateur,
        ]);
    }

    public function findByUtilisateur(UserInterface $utilisateur) : array {
        return $this->findBy([
            'utilisateur' => $utilisateur,
        ]);
    }

    //    /**
    //     * @return YamlFile[] Returns an array of YamlFile objects
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

    //    public function findOneBySomeField($value): ?YamlFile
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
