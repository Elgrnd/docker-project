<?php

namespace App\Repository;

use App\Entity\Groupe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Groupe>
 */
class GroupeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Groupe::class);
    }

    public function findGroupsWithVmid(): array
    {
        return $this->createQueryBuilder('g')
            ->innerJoin('g.vm', 'v')
            ->where("v.vmId IS NOT NULL and v.vmStatus = 'ready'")
            ->getQuery()
            ->getResult();
    }

}
