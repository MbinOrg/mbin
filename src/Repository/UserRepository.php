<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Entity\UserFollow;
use App\Service\SettingsManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Result;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Adapter\ArrayAdapter;
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

    public function __construct(ManagerRegistry $registry, private readonly SettingsManager $settingsManager)
    {
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

    public function countPublicActivity(User $user, bool $hideAdult): int
    {
        return $this->getPublicActivityQuery($user, $hideAdult)->rowCount();
    }

    private function getPublicActivityQuery(User $user, bool $hideAdult): Result
    {
        $falseCond = $user->isDeleted ? ' AND FALSE ' : '';
        $conn = $this->_em->getConnection();
        $sql = "SELECT id, created_at, 'entry' AS type FROM entry
            WHERE user_id = :userId AND visibility = :visibility $falseCond
            AND is_adult = CASE WHEN :hideAdult THEN false ELSE is_adult END
        UNION
        SELECT id, created_at, 'entry_comment' AS type FROM entry_comment
            WHERE user_id = :userId AND visibility = :visibility $falseCond
            AND is_adult = CASE WHEN :hideAdult THEN false ELSE is_adult END
        UNION
        SELECT id, created_at, 'post' AS type FROM post
            WHERE user_id = :userId AND visibility = :visibility $falseCond
            AND is_adult = CASE WHEN :hideAdult THEN false ELSE is_adult END
        UNION
        SELECT id, created_at, 'post_comment' AS type FROM post_comment
            WHERE user_id = :userId AND visibility = :visibility $falseCond
            AND is_adult = CASE WHEN :hideAdult THEN false ELSE is_adult END
        ORDER BY created_at DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('userId', $user->getId());
        $stmt->bindValue('visibility', VisibilityInterface::VISIBILITY_VISIBLE);
        $stmt->bindValue('hideAdult', $hideAdult, \PDO::PARAM_BOOL);

        return $stmt->executeQuery();
    }

    public function findPublicActivity(int $page, User $user, bool $hideAdult): PagerfantaInterface
    {
        // @todo union adapter
        $stmt = $this->getPublicActivityQuery($user, $hideAdult);

        $pagerfanta = new Pagerfanta(
            new ArrayAdapter(
                $stmt->fetchAllAssociative()
            )
        );

        $countAll = $pagerfanta->count();

        try {
            $pagerfanta->setMaxPerPage(20000);
            $pagerfanta->setCurrentPage(1);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        $result = $pagerfanta->getCurrentPageResults();

        $entries = $this->_em->getRepository(Entry::class)->findBy(
            ['id' => $this->getOverviewIds((array) $result, 'entry')]
        );
        $entryComments = $this->_em->getRepository(EntryComment::class)->findBy(
            ['id' => $this->getOverviewIds((array) $result, 'entry_comment')]
        );
        $post = $this->_em->getRepository(Post::class)->findBy(['id' => $this->getOverviewIds((array) $result, 'post')]);
        $postComment = $this->_em->getRepository(PostComment::class)->findBy(
            ['id' => $this->getOverviewIds((array) $result, 'post_comment')]
        );

        $result = array_merge($entries, $entryComments, $post, $postComment);
        uasort($result, fn ($a, $b) => $a->getCreatedAt() > $b->getCreatedAt() ? -1 : 1);

        $pagerfanta = new Pagerfanta(
            new ArrayAdapter(
                $result
            )
        );

        try {
            $pagerfanta->setMaxPerPage(self::PER_PAGE);
            $pagerfanta->setCurrentPage($page);
            $pagerfanta->setMaxNbPages($countAll > 0 ? ((int) ceil($countAll / self::PER_PAGE)) : 1);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    private function getOverviewIds(array $result, string $type): array
    {
        $result = array_filter($result, fn ($subject) => $subject['type'] === $type);

        return array_map(fn ($subject) => $subject['id'], $result);
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

    public function findAllActivePaginated(int $page, bool $onlyLocal = false): PagerfantaInterface
    {
        $builder = $this->createQueryBuilder('u');
        if ($onlyLocal) {
            $builder->where('u.apId IS NULL')
            ->andWhere('u.isVerified = true');
        } else {
            $builder->where('u.apId IS NOT NULL');
        }
        $query = $builder
            ->andWhere('u.visibility = :visibility')
            ->andWhere('u.isDeleted = false')
            ->andWhere('u.isBanned = false')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery();

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

    public function findAllInactivePaginated(int $page): PagerfantaInterface
    {
        $builder = $this->createQueryBuilder('u');

        $query = $builder->where('u.apId IS NULL')
            ->andWhere('u.visibility = :visibility')
            ->andWhere('u.isVerified = false')
            ->andWhere('u.isDeleted = false')
            ->andWhere('u.isBanned = false')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery();

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

    public function findAllBannedPaginated(int $page, bool $onlyLocal = false): PagerfantaInterface
    {
        $builder = $this->createQueryBuilder('u');
        if ($onlyLocal) {
            $builder->where('u.apId IS NULL');
        } else {
            $builder->where('u.apId IS NOT NULL');
        }
        $query = $builder
            ->andWhere('u.isBanned = true')
            ->andWhere('u.isDeleted = false')
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery();

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

    public function findAllSuspendedPaginated(int $page, bool $onlyLocal = false): PagerfantaInterface
    {
        $builder = $this->createQueryBuilder('u');
        if ($onlyLocal) {
            $builder->where('u.apId IS NULL');
        } else {
            $builder->where('u.apId IS NOT NULL');
        }
        $query = $builder
            ->andWhere('u.visibility = :visibility')
            ->andWhere('u.isDeleted = false')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_TRASHED)
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery();

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

    public function findForDeletionPaginated(int $page): PagerfantaInterface
    {
        $query = $this->createQueryBuilder('u')
            ->where('u.apId IS NULL')
            ->andWhere('u.visibility = :visibility')
            ->orderBy('u.markedForDeletionAt', 'ASC')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_SOFT_DELETED)
            ->getQuery();

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
            ->setParameter('username', $username)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByUsernames(array $users): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.username IN (?1)')
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
            ->orderBy('u.lastActive', 'DESC');
    }

    public function findWithAboutPaginated(
        int $page,
        string $group = self::USERS_ALL,
        int $perPage = self::PER_PAGE
    ): PagerfantaInterface {
        $query = $this->findWithAboutQueryBuilder($group)->getQuery();

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

    private function findWithAboutQueryBuilder(string $group): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.about != \'\'')
            ->andWhere('u.about IS NOT NULL');

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
        int $perPage = self::PER_PAGE
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
            ->getQuery()
            ->getResult();
    }

    public function findUsersSuggestions(string $query): array
    {
        $qb = $this->createQueryBuilder('u');

        return $qb
            ->andWhere($qb->expr()->like('u.username', ':query'))
            ->orWhere($qb->expr()->like('u.email', ':query'))
            ->andWhere('u.isBanned = false')
            ->andWhere('u.isDeleted = false')
            ->setParameters(['query' => "{$query}%"])
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    public function findUsersForMagazine(Magazine $magazine, ?bool $federated = false, $limit = 200, bool $limitTime = false, bool $requireAvatar = false): array
    {
        $conn = $this->_em->getConnection();
        $timeWhere = $limitTime ? "AND created_at > now() - '30 days'::interval" : '';
        $sql = "
        (SELECT count(id), user_id FROM entry WHERE magazine_id = :magazineId $timeWhere GROUP BY user_id ORDER BY count DESC LIMIT 50)
        UNION
        (SELECT count(id), user_id FROM entry_comment WHERE magazine_id = :magazineId $timeWhere GROUP BY user_id ORDER BY count DESC LIMIT 50)
        UNION
        (SELECT count(id), user_id FROM post WHERE magazine_id = :magazineId $timeWhere GROUP BY user_id ORDER BY count DESC LIMIT 50)
        UNION
        (SELECT count(id), user_id FROM post_comment WHERE magazine_id = :magazineId $timeWhere GROUP BY user_id ORDER BY count DESC LIMIT 50)
        ORDER BY count DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('magazineId', $magazine->getId());
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

        $user = array_map(fn ($item) => $item['user_id'], $output);

        $qb = $this->createQueryBuilder('u', 'u.id');
        $qb->andWhere($qb->expr()->in('u.id', $user))
            ->andWhere('u.isBanned = false')
            ->andWhere('u.isDeleted = false')
            ->andWhere('u.visibility = :visibility')
            ->andWhere('u.apDeletedAt IS NULL')
            ->andWhere('u.apTimeoutAt IS NULL');

        if (true === $requireAvatar) {
            $qb->andWhere('u.avatar IS NOT NULL');
        }

        if (null !== $federated) {
            if ($federated) {
                $qb->andWhere('u.apId IS NOT NULL')
                    ->andWhere('u.apDiscoverable = true');
            } else {
                $qb->andWhere('u.apId IS NULL');
            }
        }

        $qb->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
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
            if (\count($res) >= 35) {
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
                ->andWhere('u.lastActive >= :lastActive')
                ->andWhere('u.isBanned = false')
                ->andWhere('u.isDeleted = false')
                ->andWhere('u.visibility = :visibility')
                ->andWhere('u.apDeletedAt IS NULL')
                ->andWhere('u.apTimeoutAt IS NULL')
                ->andWhere('u.avatar IS NOT NULL');
            if ($this->settingsManager->get('MBIN_SIDEBAR_SECTIONS_LOCAL_ONLY')) {
                $results = $results->andWhere('u.apId IS NULL');
            }

            $results = $results->join('u.avatar', 'a')
                ->orderBy('u.lastActive', 'DESC')
                ->setParameters(['lastActive' => (new \DateTime())->modify('-7 days'), 'visibility' => VisibilityInterface::VISIBILITY_VISIBLE])
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
}
