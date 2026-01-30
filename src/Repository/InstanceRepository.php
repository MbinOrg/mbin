<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Instance;
use App\Entity\Magazine;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Instance|null find($id, $lockMode = null, $lockVersion = null)
 * @method Instance|null findOneBy(array $criteria, array $orderBy = null)
 * @method Instance[]    findAll()
 * @method Instance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class InstanceRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Instance::class);
    }

    public function getInstanceOfUser(User $user): ?Instance
    {
        return $this->findOneBy(['domain' => $user->apDomain]);
    }

    public function getInstanceOfMagazine(Magazine $magazine): ?Instance
    {
        return $this->findOneBy(['domain' => $magazine->apDomain]);
    }

    /** @return Instance[] */
    public function getAllowedInstances(bool $useAllowlist): array
    {
        $qb = $this->createQueryBuilder('i');

        if ($useAllowlist) {
            $qb->Where('i.isExplicitlyAllowed = true');
        } else {
            $qb->where('i.isBanned = false');
        }

        return $qb
            ->orderBy('i.domain')
            ->getQuery()
            ->getResult();
    }

    /** @return Instance[] */
    public function getBannedInstances(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.isBanned = true')
            ->andWhere('i.isExplicitlyAllowed = false')
            ->orderBy('i.domain')
            ->getQuery()
            ->getResult();
    }

    /** @return Instance[] */
    public function getDeadInstances(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.failedDelivers >= :numToDead')
            ->andWhere('i.lastSuccessfulDeliver < :dateBeforeDead OR i.lastSuccessfulDeliver IS NULL')
            ->andWhere('i.lastSuccessfulReceive < :dateBeforeDead OR i.lastSuccessfulReceive IS NULL')
            ->setParameter('numToDead', Instance::NUMBER_OF_FAILED_DELIVERS_UNTIL_DEAD)
            ->setParameter('dateBeforeDead', Instance::getDateBeforeDead())
            ->orderBy('i.domain')
            ->getQuery()
            ->getResult();
    }

    public function getOrCreateInstance(string $domain): Instance
    {
        $instance = $this->findOneBy(['domain' => $domain]);
        if (null !== $instance) {
            return $instance;
        }

        $instance = new Instance($domain);
        $this->getEntityManager()->persist($instance);
        $this->getEntityManager()->flush();

        return $instance;
    }

    /** @return string[] */
    public function getBannedInstanceUrls(): array
    {
        return array_map(fn (Instance $i) => $i->domain, $this->getBannedInstances());
    }

    /**
     * @return array{magazines: int, users: int, theirUserFollows: int, ourUserFollows: int, theirSubscriptions: int, ourSubscriptions: int}
     *
     * @throws \Doctrine\DBAL\Exception
     */
    public function getInstanceCounts(Instance $instance): array
    {
        $sql = "SELECT 'users' as type, COUNT(u.id) as c FROM instance i
                INNER JOIN \"user\" u ON u.ap_domain = i.domain
                WHERE u.is_banned = false AND u.is_deleted = false AND u.visibility = :visible AND u.marked_for_deletion_at IS NULL AND i.domain = :domain
            UNION
            SELECT 'magazines' as type, COUNT(m.id) as c FROM instance i
                INNER JOIN magazine m ON m.ap_domain = i.domain
                WHERE m.visibility = :visible AND m.ap_deleted_at IS NULL AND m.marked_for_deletion_at IS NULL AND i.domain = :domain
            UNION
            SELECT 'theirUserFollows' as type, COUNT(uf.id) as c FROM instance i
                INNER JOIN \"user\" u ON u.ap_domain = i.domain
                INNER JOIN user_follow uf ON uf.follower_id = u.id
                INNER JOIN \"user\" u2 ON uf.following_id = u2.id AND u2.ap_id IS NULL
                WHERE u.is_banned = false AND u.is_deleted = false AND u.visibility = :visible AND u.marked_for_deletion_at IS NULL AND i.domain = :domain
                    AND u2.is_banned = false AND u2.is_deleted = false AND u.visibility = :visible AND u2.marked_for_deletion_at IS NULL
            UNION
            SELECT 'ourUserFollows' as type, COUNT(uf.id) as c FROM instance i
                INNER JOIN \"user\" u ON u.ap_domain = i.domain
                INNER JOIN user_follow uf ON uf.following_id = u.id
                INNER JOIN \"user\" u2 ON uf.follower_id = u2.id AND u2.ap_id IS NULL
                WHERE u.is_banned = false AND u.is_deleted = false AND u.visibility = :visible AND u.marked_for_deletion_at IS NULL AND i.domain = :domain
                    AND u2.is_banned = false AND u2.is_deleted = false AND u.visibility = :visible AND u2.marked_for_deletion_at IS NULL
            UNION
            SELECT 'theirSubscriptions' as type, COUNT(ms.id) as c FROM instance i
                INNER JOIN \"user\" u ON u.ap_domain = i.domain
                INNER JOIN magazine_subscription ms ON ms.user_id = u.id
                INNER JOIN magazine m ON m.id = ms.magazine_id AND m.ap_id IS NULL
                WHERE u.is_banned = false AND u.is_deleted = false AND u.visibility = :visible AND u.marked_for_deletion_at IS NULL AND i.domain = :domain
                    AND m.visibility = :visible AND m.ap_deleted_at IS NULL AND m.marked_for_deletion_at IS NULL
            UNION
            SELECT 'ourSubscriptions' as type, COUNT(ms.id) as c FROM instance i
                INNER JOIN magazine m ON m.ap_domain = i.domain
                INNER JOIN magazine_subscription ms ON ms.magazine_id = m.id
                INNER JOIN \"user\" u ON u.id = ms.user_id AND u.ap_id IS NULL
                WHERE u.is_banned = false AND u.is_deleted = false AND u.visibility = :visible AND u.marked_for_deletion_at IS NULL AND i.domain = :domain
                    AND m.visibility = :visible AND m.ap_deleted_at IS NULL AND m.marked_for_deletion_at IS NULL
            ";
        $stmt = $this->getEntityManager()->getConnection()->prepare($sql);

        $stmt->bindValue('visible', VisibilityInterface::VISIBILITY_VISIBLE);
        $stmt->bindValue('domain', $instance->domain);

        $result = $stmt->executeQuery()
            ->fetchAllAssociative();

        $mappedResult = [];
        foreach ($result as $row) {
            $mappedResult[$row['type']] = $row['c'];
        }

        return [
            'magazines' => $mappedResult['magazines'],
            'users' => $mappedResult['users'],
            'ourUserFollows' => $mappedResult['ourUserFollows'],
            'theirUserFollows' => $mappedResult['theirUserFollows'],
            'ourSubscriptions' => $mappedResult['ourSubscriptions'],
            'theirSubscriptions' => $mappedResult['theirSubscriptions'],
        ];
    }

    /**
     * @return Instance[]
     */
    public function findAllOrdered(): array
    {
        $qb = $this->createQueryBuilder('i')
            ->orderBy('i.domain', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
