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
            'utilisateur_yamlfile' => $utilisateur,
        ]);
    }

    public function findByUtilisateur(UserInterface $utilisateur) : array {
        return $this->findBy([
            'utilisateur_yamlfile' => $utilisateur,
        ]);
    }

    public function findForUser(Utilisateur $utilisateur, bool $isAdmin): array
    {
        $qb = $this->createQueryBuilder('y');

        if (!$isAdmin) {
            $qb->where('y.utilisateur_yamlfile = :utilisateur')
                ->setParameter('utilisateur', $utilisateur);
        }

        return $qb->orderBy('y.nameFile', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function recupererYamlFileSansUtilisateur()
    {
        return $this->createQueryBuilder('y')
            ->andWhere('y.utilisateur_yamlfile IS NULL')
            ->orderBy('y.nameFile', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function existeDansBiblio(string $nameFile): bool
    {
        return (bool) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.nameFile = :nameFile')
            ->andWhere('u.utilisateur_yamlfile IS NULL')
            ->setParameter('nameFile', $nameFile)
            ->getQuery()
            ->getSingleScalarResult();
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
