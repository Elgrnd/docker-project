<?php

namespace App\Repository;

use App\Entity\Groupe;
use App\Entity\Utilisateur;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function getUtilisateursAvecVm(): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.vm', 'vm')
            ->where("vm.vmStatus IS NOT NULL AND vm.vmStatus = 'ready'")
            ->getQuery()
            ->getResult();
    }


    public function findAllExcept(Utilisateur $utilisateur): array
    {
        return $this->createQueryBuilder('u')
            ->where('u != :utilisateur')
            ->setParameter('utilisateur', $utilisateur)
            ->orderBy('u.login', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findNonMembresDuGroupe(Groupe $groupe): array
    {
        $qb = $this->createQueryBuilder('u');
        $em = $this->getEntityManager();

        return $qb
            ->where($qb->expr()->notIn(
                'u.id',
                $em->createQueryBuilder()
                    ->select('IDENTITY(ug_sub.utilisateur)')
                    ->from('App\Entity\UtilisateurGroupe', 'ug_sub')
                    ->where('ug_sub.groupe = :groupe')
                    ->getDQL()
            ))
            ->setParameter('groupe', $groupe)
            ->orderBy('u.login', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findUsersWithExpiredVm(DateTimeImmutable $now)
    {
        return $this->createQueryBuilder('u')
            ->where('u.deleteVmAt IS NOT NULL')
            ->andWhere('u.deleteVmAt <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}