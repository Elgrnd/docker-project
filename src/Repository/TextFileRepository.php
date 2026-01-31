<?php

namespace App\Repository;

use App\Entity\TextFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<TextFile>
 */
class TextFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TextFile::class);
    }

    public function findByUtilisateur(UserInterface $utilisateur): array
    {
        return $this->findBy([
            'utilisateur_file' => $utilisateur,
        ]);
    }

    public function recupererTextFileSansUtilisateur(): array
    {
        return $this->createQueryBuilder('y')
            ->andWhere('y.utilisateur_file IS NULL')
            ->orderBy('y.nameFile', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function existeDansBiblio(string $nameFile): bool
    {
        return (bool) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.nameFile = :nameFile')
            ->andWhere('u.utilisateur_file IS NULL')
            ->setParameter('nameFile', $nameFile)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
