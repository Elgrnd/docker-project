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

    public function findByOwner(Utilisateur $user): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.file', 'f')
            ->where('f.utilisateur_file = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}
