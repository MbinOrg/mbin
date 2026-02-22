<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Magazine;
use App\Entity\User;
use App\Entity\UserFollow;
use App\Enums\EApplicationStatus;
use App\Service\SettingsManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Doctrine\Collections\CollectionAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface, PasswordUpgraderInterface
{
    public const PER_PAGE = 48;
    public const USERS_ALL = 'all';
    public const USERS_LOCAL = 'local';
    public const USERS_REMOTE = 'remote';
    public const USERS_OPTIONS = [
        self::USERS_ALL,
        self::USERS_LOCAL,
        self::USERS_REMOTE,
    ];

    public function __construct(
        ManagerRegistry $registry,
        private readonly SettingsManager $settingsManager,
    ) {
        parent::__construct($registry, User::class);
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function loadUserByUsername(string $username): ?User
    {
        return $this->loadUserByIdentifier($username);
    }

    public function loadUserByIdentifier($val): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.username) = :email')
            ->orWhere('LOWER(u.email) = :email')
            ->setParameter('email', mb_strtolower($val))
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findFollowing(int $page, User $user, int $perPage = self::PER_PAGE): PagerfantaInterface
    {
        $pagerfanta = new Pagerfanta(
            new CollectionAdapter(
                $user->follows
            )
        );

        try {
            $pagerfanta->setMaxPerPage($perPage);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    public function findFollowers(int $page, User $user, int $perPage = self::PER_PAGE): PagerfantaInterface
    {
        $pagerfanta = new Pagerfanta(
            new CollectionAdapter(
                $user->followers
            )
        );

        try {
            $pagerfanta->setMaxPerPage($perPage);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    public function findAudience(User $user): array
    {
        $dql =
            'SELECT COUNT(u.id), u.apInboxUrl FROM '.User::class.' u WHERE u IN ('.
            'SELECT IDENTITY(us.follower) FROM '.UserFollow::class.' us WHERE us.following = :user)'.
            'AND u.apId IS NOT NULL AND u.isBanned = false AND u.isDeleted = false AND u.apTimeoutAt IS NULL '.
            'GROUP BY u.apInboxUrl';

        $res = $this->getEntityManager()->createQuery($dql)
            ->setParameter('user', $user)
            ->getResult();

        return array_map(fn ($item) => $item['apInboxUrl'], $res);
    }

    public function findBlockedUsers(int $page, User $user, int $perPage = self::PER_PAGE): PagerfantaInterface
    {
        $pagerfanta = new Pagerfanta(
            new CollectionAdapter(
                $user->blocks
            )
        );

        try {
            $pagerfanta->setMaxPerPage($perPage);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    public function findAllActivePaginated(int $page, bool $onlyLocal, ?string $searchTerm = null, ?OrderBy $orderBy = null): PagerfantaInterface
    {
        $builder = $this->createBasicQueryBuilder($onlyLocal, $searchTerm);

        $builder
            ->andWhere('u.visibility = :visibility')
            ->andWhere('u.isDeleted = false')
            ->andWhere('u.isBanned = false')
            ->andWhere('u.applicationStatus = :status')
            ->setParameter('status', EApplicationStatus::Approved->value)
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE);

        return $this->executeBasicQueryBuilder($builder, $page, $orderBy);
    }

    public function findAllInactivePaginated(int $page, bool $onlyLocal = true, ?string $searchTerm = null, ?OrderBy $orderBy = null): PagerfantaInterface
    {
        $builder = $this->createBasicQueryBuilder($onlyLocal, $searchTerm, needToBeVerified: false);

        $builder->andWhere('u.visibility = :visibility')
            ->andWhere('u.isVerified = false')
            ->andWhere('u.isDeleted = false')
            ->andWhere('u.isBanned = false')
            ->andWhere('u.applicationStatus = :status')
            ->setParameter('status', EApplicationStatus::Approved->value)
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE);

        return $this->executeBasicQueryBuilder($builder, $page, $orderBy);
    }

    public function findAllBannedPaginated(int $page, bool $onlyLocal = false, ?string $searchTerm = null, ?OrderBy $orderBy = null): PagerfantaInterface
    {
        $builder = $this->createBasicQueryBuilder($onlyLocal, $searchTerm);
        $builder
            ->andWhere('u.isBanned = true')
            ->andWhere('u.isDeleted = false');

        return $this->executeBasicQueryBuilder($builder, $page, $orderBy);
    }

    public function findAllSuspendedPaginated(int $page, bool $onlyLocal = false, ?string $searchTerm = null, ?OrderBy $orderBy = null): PagerfantaInterface
    {
        $builder = $this->createBasicQueryBuilder($onlyLocal, $searchTerm);
        $builder
            ->andWhere('u.visibility = :visibility')
            ->andWhere('u.isDeleted = false')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_TRASHED);

        return $this->executeBasicQueryBuilder($builder, $page, $orderBy);
    }

    public function findForDeletionPaginated(int $page): PagerfantaInterface
    {
        $builder = $this->createBasicQueryBuilder(onlyLocal: true, searchTerm: null)
            ->andWhere('u.visibility = :visibility')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_SOFT_DELETED);

        return $this->executeBasicQueryBuilder($builder, $page, new OrderBy('u.markedForDeletionAt', 'ASC'));
    }

    /**
     * @param bool|null $needToBeVerified this is only relevant if $onlyLocal is true, requires the user to be verified
     */
    private function createBasicQueryBuilder(bool $onlyLocal, ?string $searchTerm, ?bool $needToBeVerified = true): QueryBuilder
    {
        $builder = $this->createQueryBuilder('u');
        if ($onlyLocal) {
            $builder->where('u.apId IS NULL');
            if ($needToBeVerified) {
                $builder->andWhere('u.isVerified = true');
            }
        } else {
            $builder->where('u.apId IS NOT NULL');
        }

        if ($searchTerm) {
            $builder
                ->andWhere('lower(u.username) LIKE lower(:searchTerm) OR lower(u.email) LIKE lower(:searchTerm)')
                ->setParameter('searchTerm', '%'.$searchTerm.'%');
        }

        return $builder;
    }

    private function executeBasicQueryBuilder(QueryBuilder $builder, int $page, ?OrderBy $orderBy = null): Pagerfanta
    {
        if (null === $orderBy) {
            $orderBy = new OrderBy('u.createdAt', 'ASC');
        }

        $query = $builder
            ->orderBy($orderBy)
            ->getQuery();

        $pagerfanta = new Pagerfanta(new QueryAdapter($query));

        try {
            $pagerfanta->setMaxPerPage(self::PER_PAGE);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);

        $this->_em->persist($user);
        $this->_em->flush();
    }

    public function findOneByUsername(string $username): ?User
    {
        return $this->createQueryBuilder('u')
            ->Where('LOWER(u.username) = LOWER(:username)')
            ->andWhere('u.applicationStatus = :status')
            ->setParameter('status', EApplicationStatus::Approved->value)
            ->setParameter('username', $username)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByUsernames(array $users): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.username IN (?1)')
            ->andWhere('u.applicationStatus = :status')
            ->setParameter('status', EApplicationStatus::Approved->value)
            ->setParameter(1, $users)
            ->getQuery()
            ->getResult();
    }

    public function findWithoutKeys(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.privateKey IS NULL')
            ->andWhere('u.apId IS NULL')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[]
     */
    public function findAllRemote(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.apId IS NOT NULL')
            ->getQuery()
            ->getResult();
    }

    public function findRemoteForUpdate(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.apId IS NOT NULL')
            ->andWhere('u.apDomain IS NULL')
            ->andWhere('u.apDeletedAt IS NULL')
            ->andWhere('u.apTimeoutAt IS NULL')
            ->addOrderBy('u.apFetchedAt', 'ASC')
            ->setMaxResults(1000)
            ->getQuery()
            ->getResult();
    }

    private function findUsersQueryBuilder(string $group, ?bool $recentlyActive = true): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u');

        $qb->where('u.visibility = :visibility')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE);

        if ($recentlyActive) {
            $qb->andWhere('u.lastActive >= :lastActive')
                ->setParameter('lastActive', (new \DateTime())->modify('-7 days'));
        }

        switch ($group) {
            case self::USERS_LOCAL:
                $qb->andWhere('u.apId IS NULL');
                break;
            case self::USERS_REMOTE:
                $qb->andWhere('u.apId IS NOT NULL')
                    ->andWhere('u.apDiscoverable = true');
                break;
        }

        return $qb
            ->andWhere('u.isDeleted = false')
            ->andWhere('u.apDiscoverable = true')
            ->andWhere('u.applicationStatus = :status')
            ->setParameter('status', EApplicationStatus::Approved->value)
            ->orderBy('u.lastActive', 'DESC');
    }

    public function findPaginated(int $page, bool $needsAbout, string $group = self::USERS_ALL, int $perPage = self::PER_PAGE, ?string $query = null): PagerfantaInterface
    {
        $query = $this->findQueryBuilder($group, $query, $needsAbout)->getQuery();

        $pagerfanta = new Pagerfanta(
            new QueryAdapter(
                $query
            )
        );

        try {
            $pagerfanta->setMaxPerPage($perPage);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    private function findQueryBuilder(string $group, ?string $query, bool $needsAbout): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u');

        if ($needsAbout) {
            $qb->andWhere('u.about != \'\'')
                ->andWhere('u.about IS NOT NULL');
        }

        if (null !== $query) {
            $qb->andWhere('u.username LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        switch ($group) {
            case self::USERS_LOCAL:
                $qb->andWhere('u.apId IS NULL');
                break;
            case self::USERS_REMOTE:
                $qb->andWhere('u.apId IS NOT NULL')
                    ->andWhere('u.apDiscoverable = true');
                break;
        }

        return $qb
            ->andWhere('u.applicationStatus = :status')
            ->setParameter('status', EApplicationStatus::Approved->value)
            ->orderBy('u.lastActive', 'DESC');
    }

    public function findUsersForGroup(string $group = self::USERS_ALL, ?bool $recentlyActive = true): array
    {
        return $this->findUsersQueryBuilder($group, $recentlyActive)->setMaxResults(28)->getQuery()->getResult();
    }

    private function findBannedQueryBuilder(string $group): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.isBanned = true');

        switch ($group) {
            case self::USERS_LOCAL:
                $qb->andWhere('u.apId IS NULL');
                break;
            case self::USERS_REMOTE:
                $qb->andWhere('u.apId IS NOT NULL')
                    ->andWhere('u.apDiscoverable = true');
                break;
        }

        return $qb->orderBy('u.lastActive', 'DESC');
    }

    public function findBannedPaginated(
        int $page,
        string $group = self::USERS_ALL,
        int $perPage = self::PER_PAGE,
    ): PagerfantaInterface {
        $query = $this->findBannedQueryBuilder($group)->getQuery();

        $pagerfanta = new Pagerfanta(
            new QueryAdapter(
                $query
            )
        );

        try {
            $pagerfanta->setMaxPerPage($perPage);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    public function findAdmin(): User
    {
        // @todo orderBy lastActivity
        $result = $this->createQueryBuilder('u')
            ->andWhere("JSONB_CONTAINS(u.roles, '\"".'ROLE_ADMIN'."\"') = true")
            ->andWhere('u.isDeleted = false')
            ->andWhere('u.applicationStatus = :status')
            ->setParameter('status', EApplicationStatus::Approved->value)
            ->getQuery()
            ->getResult();
        if (0 === \sizeof($result)) {
            throw new \Exception('the server must always have an active admin account');
        }

        return $result[0];
    }

    /**
     * @return User[]
     */
    public function findAllAdmins(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere("JSONB_CONTAINS(u.roles, '\"".'ROLE_ADMIN'."\"') = true")
            ->andWhere('u.isDeleted = false')
            ->andWhere('u.applicationStatus = :status')
            ->setParameter('status', EApplicationStatus::Approved->value)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return User[]
     */
    public function findUsersSuggestions(string $query): array
    {
        $qb = $this->createQueryBuilder('u');

        return $qb
            ->andWhere($qb->expr()->like('u.username', ':query'))
            ->orWhere($qb->expr()->like('u.email', ':query'))
            ->andWhere('u.isBanned = false')
            ->andWhere('u.isDeleted = false')
            ->andWhere('u.applicationStatus = :status')
            ->setParameters(['query' => "{$query}%", 'status' => EApplicationStatus::Approved->value])
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    public function findUsersForMagazine(Magazine $magazine, ?bool $federated = false, int $limit = 200, bool $limitTime = false, bool $requireAvatar = false): array
    {
        $conn = $this->_em->getConnection();
        $timeWhere = $limitTime ? "AND created_at > now() - '30 days'::interval" : '';
        $sql = "
        (SELECT count(id), user_id FROM entry WHERE magazine_id = :magazineId $timeWhere GROUP BY user_id ORDER BY count DESC LIMIT :limit)
        UNION ALL
        (SELECT count(id), user_id FROM entry_comment WHERE magazine_id = :magazineId $timeWhere GROUP BY user_id ORDER BY count DESC LIMIT :limit)
        UNION ALL
        (SELECT count(id), user_id FROM post WHERE magazine_id = :magazineId $timeWhere GROUP BY user_id ORDER BY count DESC LIMIT :limit)
        UNION ALL
        (SELECT count(id), user_id FROM post_comment WHERE magazine_id = :magazineId $timeWhere GROUP BY user_id ORDER BY count DESC LIMIT :limit)
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('magazineId', $magazine->getId());
        $stmt->bindValue('limit', $limit);
        $counter = $stmt->executeQuery()->fetchAllAssociative();

        $output = [];
        foreach ($counter as $item) {
            $user_id = $item['user_id'];
            $count = $item['count'];
            if (isset($output[$user_id])) {
                $output[$user_id]['count'] += $count;
            } else {
                $output[$user_id] = ['count' => $count, 'user_id' => $user_id];
            }
        }

        // sort the array after the counts from the different table are added up
        usort($output, fn ($a, $b) => $b['count'] - $a['count']);

        $user = array_map(fn ($item) => $item['user_id'], $output);

        $qb = $this->createQueryBuilder('u', 'u.id');
        $qb->andWhere($qb->expr()->in('u.id', $user))
            ->andWhere('u.isBanned = false')
            ->andWhere('u.isDeleted = false')
            ->andWhere('u.applicationStatus = :status')
            ->andWhere('u.visibility = :visibility')
            ->andWhere('u.apDiscoverable = true')
            ->andWhere('u.apDeletedAt IS NULL')
            ->andWhere('u.apTimeoutAt IS NULL');

        if (true === $requireAvatar) {
            $qb->andWhere('u.avatar IS NOT NULL');
        }

        if (null !== $federated) {
            if ($federated) {
                $qb->andWhere('u.apId IS NOT NULL');
            } else {
                $qb->andWhere('u.apId IS NULL');
            }
        }

        $qb->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
            ->setParameter('status', EApplicationStatus::Approved->value)
            ->setMaxResults($limit);

        try {
            $users = $qb->getQuery()->getResult(); // @todo
        } catch (\Exception $e) {
            return [];
        }

        $res = [];
        foreach ($output as $item) {
            if (isset($users[$item['user_id']])) {
                $res[] = $users[$item['user_id']];
            }
            if (\count($res) >= $limit) {
                break;
            }
        }

        return $res;
    }

    public function findActiveUsers(?Magazine $magazine = null)
    {
        if ($magazine) {
            $results = $this->findUsersForMagazine($magazine, null, 35, true, true);
        } else {
            $results = $this->createQueryBuilder('u')
                ->andWhere('u.applicationStatus = :status')
                ->andWhere('u.lastActive >= :lastActive')
                ->andWhere('u.isBanned = false')
                ->andWhere('u.isDeleted = false')
                ->andWhere('u.visibility = :visibility')
                ->andWhere('u.apDiscoverable = true')
                ->andWhere('u.apDeletedAt IS NULL')
                ->andWhere('u.apTimeoutAt IS NULL')
                ->andWhere('u.avatar IS NOT NULL');
            if ($this->settingsManager->get('MBIN_SIDEBAR_SECTIONS_USERS_LOCAL_ONLY')) {
                $results = $results->andWhere('u.apId IS NULL');
            }

            $results = $results->join('u.avatar', 'a')
                ->orderBy('u.lastActive', 'DESC')
                ->setParameters(['lastActive' => (new \DateTime())->modify('-7 days'), 'visibility' => VisibilityInterface::VISIBILITY_VISIBLE, 'status' => EApplicationStatus::Approved->value])
                ->setMaxResults(35)
                ->getQuery()
                ->getResult();
        }

        shuffle($results);

        return \array_slice($results, 0, 12);
    }

    public function findByProfileIds(array $arr): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.apProfileId IN (:arr)')
            ->setParameter('arr', $arr)
            ->getQuery()
            ->getResult();
    }

    public function findModerators(int $page = 1): PagerfantaInterface
    {
        $query = $this->createQueryBuilder('u')
            ->where("JSONB_CONTAINS(u.roles, '\"".'ROLE_MODERATOR'."\"') = true")
            ->andWhere('u.visibility = :visibility')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE);

        $pagerfanta = new Pagerfanta(
            new QueryAdapter(
                $query
            )
        );

        try {
            $pagerfanta->setMaxPerPage(self::PER_PAGE);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    /**
     * @return User[]
     */
    public function findAllModerators(): array
    {
        return $this->createQueryBuilder('u')
            ->where("JSONB_CONTAINS(u.roles, '\"".'ROLE_MODERATOR'."\"') = true")
            ->andWhere('u.visibility = :visibility')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findAllSignupRequestsPaginated(int $page = 1): PagerfantaInterface
    {
        $query = $this->createQueryBuilder('u')
            ->where('u.applicationStatus = :status')
            ->andWhere('u.apId IS NULL')
            ->andWhere('u.isDeleted = false')
            ->andWhere('u.markedForDeletionAt IS NULL')
            ->setParameter('status', EApplicationStatus::Pending->value)
            ->getQuery();

        $fanta = new Pagerfanta(new QueryAdapter($query));
        $fanta->setCurrentPage($page);
        $fanta->setMaxPerPage(self::PER_PAGE);

        return $fanta;
    }

    public function findSignupRequest(string $username): ?User
    {
        return $this->createQueryBuilder('u')
            ->where('u.applicationStatus = :status')
            ->andWhere('u.apId IS NULL')
            ->andWhere('u.isDeleted = false')
            ->andWhere('u.markedForDeletionAt IS NULL')
            ->andWhere('u.username = :username')
            ->setParameter('status', EApplicationStatus::Pending->value)
            ->setParameter('username', $username)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOldestUser(): ?User
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.apId IS NULL')
            ->orderBy('u.createdAt', Order::Ascending->value);

        $result = $qb->setMaxResults(1)
            ->getQuery()
            ->getResult();

        if (0 === \count($result)) {
            return null;
        }

        return $result[0];
    }
}
