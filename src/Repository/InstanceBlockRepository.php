<?php

namespace App\Repository;

use App\Entity\Instance;
use App\Entity\InstanceBlock;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InstanceBlockRepository extends ServiceEntityRepository
{

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstanceBlock::class);
    }

    /**
     * @param User $user
     * @return InstanceBlock[]
     */
    public function findBlocksForUser(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.user = :user')
            ->setParameter('user', $user)
            ->getQuery()->getResult();
    }

    public function findByUserAndInstance(User $user, Instance $instance): ?InstanceBlock {
        return $this->createQueryBuilder('b')
            ->where('b.user = :user')->andWhere('b.instance = :instance')
            ->setParameter('user', $user)->setParameter('instance', $instance)
            ->getQuery()->getOneOrNullResult();
    }
}
