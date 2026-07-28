<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Instance;
use App\Entity\InstanceBlock;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @psalm-pure
 */
class InstanceBlockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InstanceBlock::class);
    }

    /**
     * @return InstanceBlock[]
     */
    public function findBlocksForUser(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.user = :user')
            ->setParameter('user', $user)
            ->getQuery()->getResult();
    }

    public function findByUserAndInstance(User $user, Instance $instance): ?InstanceBlock
    {
        return $this->createQueryBuilder('b')
            ->where('b.user = :user')->andWhere('b.instance = :instance')
            ->setParameter('user', $user)->setParameter('instance', $instance)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * @return Instance[]
     */
    public function findAllGlobalBlockedInstances(): array
    {
        return $this->getEntityManager()
            ->createQuery('SELECT i FROM App\Entity\Instance i INNER JOIN App\Entity\InstanceBlock b ON i.id = b.instance WHERE b.blockedByAdmin = true')
            ->getResult();
    }

    public function insertForAllUsers(Instance $instance, bool $excludeAdmins = true): void
    {
        if ($excludeAdmins) {
            $excludeAdminClause = 'WHERE NOT (u.roles @> \'["ROLE_ADMIN"]\')';
        } else {
            $excludeAdminClause = '';
        }

        $stmt = $this->getEntityManager()->getConnection()
            ->prepare('INSERT INTO instance_block SELECT nextval(\'instance_block_id_seq\'), u.id, :instance, :domain, true FROM "user" u '.$excludeAdminClause.' ON CONFLICT DO NOTHING;');
        $stmt->bindValue('instance', $instance->getId(), ParameterType::INTEGER);
        $stmt->bindValue('domain', $instance->domain, ParameterType::STRING);
        $stmt->executeStatement();
    }

    public function deleteForAllUsers(Instance $instance): void
    {
        $this->getEntityManager()->createQueryBuilder()
            ->delete(InstanceBlock::class, 'b')
            ->where('b.instance = :instance')
            ->andWhere('b.blockedByAdmin = true')
            ->setParameter('instance', $instance)
            ->getQuery()->execute();
    }
}
