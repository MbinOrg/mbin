<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserPushSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method UserPushSubscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserPushSubscription|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserPushSubscription[]    findAll()
 * @method UserPushSubscription[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserPushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPushSubscription::class);
    }
}
