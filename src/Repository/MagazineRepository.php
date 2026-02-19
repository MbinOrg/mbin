<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Magazine;
use App\Entity\MagazineSubscription;
use App\Entity\Moderator;
use App\Entity\Report;
use App\Entity\User;
use App\PageView\MagazinePageView;
use App\Pagination\NativeQueryAdapter;
use App\Pagination\Transformation\ContentPopulationTransformer;
use App\Service\SettingsManager;
use App\Utils\SqlHelpers;
use App\Utils\SubscriptionSort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Doctrine\Collections\CollectionAdapter;
use Pagerfanta\Doctrine\Collections\SelectableAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @method Magazine|null find($id, $lockMode = null, $lockVersion = null)
 * @method Magazine|null findOneBy(array $criteria, array $orderBy = null)
 * @method Magazine[]    findAll()
 * @method Magazine[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MagazineRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 48;

    public const SORT_HOT = 'hot';
    public const SORT_ACTIVE = 'active';
    public const SORT_NEWEST = 'newest';
    public const SORT_OPTIONS = [
        self::SORT_ACTIVE,
        self::SORT_HOT,
        self::SORT_NEWEST,
    ];

    public function __construct(
        ManagerRegistry $registry,
        private readonly SettingsManager $settingsManager,
        private readonly SqlHelpers $sqlHelpers,
        private readonly ContentPopulationTransformer $contentPopulationTransformer,
        private readonly CacheInterface $cache,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($registry, Magazine::class);
    }

    public function save(Magazine $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findOneByName(?string $name): ?Magazine
    {
        return $this->createQueryBuilder('m')
            ->andWhere('LOWER(m.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findPaginated(MagazinePageView $criteria): PagerfantaInterface
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.visibility = :visibility')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE);

        if ($criteria->query) {
            $restrictions = 'LOWER(m.name) LIKE LOWER(:q) OR LOWER(m.title) LIKE LOWER(:q)';

            if ($criteria->fields === $criteria::FIELDS_NAMES_DESCRIPTIONS) {
                $restrictions .= ' OR LOWER(m.description) LIKE LOWER(:q)';
            }

            $qb->andWhere($restrictions)
                ->setParameter('q', '%'.trim($criteria->query).'%');
        }

        if ($criteria->showOnlyLocalMagazines()) {
            $qb->andWhere('m.apId IS NULL');
        }

        match ($criteria->adult) {
            $criteria::ADULT_HIDE => $qb->andWhere('m.isAdult = false'),
            $criteria::ADULT_ONLY => $qb->andWhere('m.isAdult = true'),
            $criteria::ADULT_SHOW => true,
        };

        match ($criteria->sortOption) {
            default => $qb->addOrderBy('m.subscriptionsCount', 'DESC'),
            $criteria::SORT_ACTIVE => $qb->addOrderBy('m.lastActive', 'DESC'),
            $criteria::SORT_NEW => $qb->addOrderBy('m.createdAt', 'DESC'),
            $criteria::SORT_THREADS => $qb->addOrderBy('m.entryCount', 'DESC'),
            $criteria::SORT_COMMENTS => $qb->addOrderBy('m.entryCommentCount', 'DESC'),
            $criteria::SORT_POSTS => $qb->addOrderBy('m.postCount + m.postCommentCount', 'DESC'),
        };

        $pagerfanta = new Pagerfanta(new QueryAdapter($qb));

        try {
            $pagerfanta->setMaxPerPage($criteria->perPage ?? self::PER_PAGE);
            $pagerfanta->setCurrentPage($criteria->page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    public function findSubscribedMagazines(int $page, User $user, int $perPage = self::PER_PAGE): PagerfantaInterface
    {
        $pagerfanta = new Pagerfanta(
            new CollectionAdapter(
                $user->subscriptions
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

    /**
     * @return Magazine[]
     */
    public function findMagazineSubscriptionsOfUser(User $user, SubscriptionSort $sort, int $max): array
    {
        $query = $this->createQueryBuilder('m')
            ->join('m.subscriptions', 'ms')
            ->join('ms.user', 'u')
            ->andWhere('u.id = :userId')
            ->setParameter('userId', $user->getId());

        if (SubscriptionSort::LastActive === $sort) {
            $query = $query
                ->orderBy('m.lastActive', 'DESC')
                ->andWhere('m.lastActive IS NOT NULL');
        } elseif (SubscriptionSort::Alphabetically === $sort) {
            $query = $query->orderBy('m.name');
        }

        $query = $query->getQuery();
        $query->setMaxResults($max);

        $goodResults = $query->getResult();
        $remaining = $max - \sizeof($goodResults);
        if ($remaining > 0) {
            $query = $this->createQueryBuilder('m')
                ->join('m.subscriptions', 'ms')
                ->join('ms.user', 'u')
                ->andWhere('u.id = :userId')
                ->andWhere('m.lastActive IS NULL')
                ->setParameter('userId', $user->getId())
                ->setMaxResults($remaining);
            $additionalResults = $query->getQuery()->getResult();
            $goodResults = array_merge($goodResults, $additionalResults);
        }

        return $goodResults;
    }

    public function findBlockedMagazines(int $page, User $user, int $perPage = self::PER_PAGE): PagerfantaInterface
    {
        $pagerfanta = new Pagerfanta(
            new CollectionAdapter(
                $user->blockedMagazines
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

    public function findModerators(
        Magazine $magazine,
        ?int $page = 1,
        int $perPage = self::PER_PAGE,
    ): PagerfantaInterface {
        $criteria = Criteria::create()
            ->orderBy(['isOwner' => 'DESC'])
            ->orderBy(['createdAt' => 'ASC']);

        $moderators = new Pagerfanta(new SelectableAdapter($magazine->moderators, $criteria));
        try {
            $moderators->setMaxPerPage($perPage);
            $moderators->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $moderators;
    }

    public function findBans(Magazine $magazine, ?int $page = 1, int $perPage = self::PER_PAGE): PagerfantaInterface
    {
        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->gt('expiredAt', new \DateTimeImmutable()))
            ->orWhere(Criteria::expr()->isNull('expiredAt'))
            ->orderBy(['createdAt' => 'DESC']);

        $bans = new Pagerfanta(new SelectableAdapter($magazine->bans, $criteria));
        try {
            $bans->setMaxPerPage($perPage);
            $bans->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $bans;
    }

    public function findReports(
        Magazine $magazine,
        ?int $page = 1,
        int $perPage = self::PER_PAGE,
        string $status = Report::STATUS_PENDING,
    ): PagerfantaInterface {
        $dql = 'SELECT r FROM '.Report::class.' r WHERE r.magazine = :magazine';

        if (Report::STATUS_ANY !== $status) {
            $dql .= ' AND r.status = :status';
        }

        $dql .= " ORDER BY CASE WHEN r.status = 'pending' THEN 1 ELSE 2 END, r.weight DESC, r.createdAt DESC";

        $query = $this->getEntityManager()->createQuery($dql);
        $query->setParameter('magazine', $magazine);

        if (Report::STATUS_ANY !== $status) {
            $query->setParameter('status', $status);
        }

        $pagerfanta = new Pagerfanta(
            new QueryAdapter($query)
        );

        try {
            $pagerfanta->setMaxPerPage(self::PER_PAGE);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    public function findBadges(Magazine $magazine): Collection
    {
        return $magazine->badges;
    }

    public function findModeratedMagazines(
        User $user,
        ?int $page = 1,
        int $perPage = self::PER_PAGE,
    ): PagerfantaInterface {
        $dql =
            'SELECT m FROM '.Magazine::class.' m WHERE m IN ('.
            'SELECT IDENTITY(md.magazine) FROM '.Moderator::class.' md WHERE md.user = :user) ORDER BY m.apId DESC, m.lastActive DESC';

        $query = $this->getEntityManager()->createQuery($dql)
            ->setParameter('user', $user);

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

    public function findTrashed(Magazine $magazine, int $page = 1, int $perPage = self::PER_PAGE): PagerfantaInterface
    {
        $magazineId = $magazine->getId();
        $sql = '
            (SELECT id, last_active, magazine_id, \'entry\' AS type FROM entry WHERE magazine_id = :magazineId AND visibility = \'trashed\')
            UNION ALL
            (SELECT id, last_active, magazine_id, \'entry_comment\' AS type FROM entry_comment WHERE magazine_id = :magazineId AND visibility = \'trashed\')
            UNION ALL
            (SELECT id, last_active, magazine_id, \'post\' AS type FROM post WHERE magazine_id = :magazineId AND visibility = \'trashed\')
            UNION ALL
            (SELECT id, last_active, magazine_id, \'post_comment\' AS type FROM post_comment WHERE magazine_id = :magazineId AND visibility = \'trashed\')
            ORDER BY last_active DESC';

        $parameters = [
            'magazineId' => $magazineId,
        ];
        $adapter = new NativeQueryAdapter($this->entityManager->getConnection(), $sql, $parameters, transformer: $this->contentPopulationTransformer, cache: $this->cache);

        $pagerfanta = new Pagerfanta($adapter);

        try {
            $pagerfanta->setMaxPerPage($perPage);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    public function findAudience(Magazine $magazine): array
    {
        if (null !== $magazine->apId) {
            return [$magazine->apInboxUrl];
        }

        $dql =
            'SELECT COUNT(u.id), u.apInboxUrl FROM '.User::class.' u WHERE u IN ('.
            'SELECT IDENTITY(ms.user) FROM '.MagazineSubscription::class.' ms WHERE ms.magazine = :magazine)'.
            'AND u.apId IS NOT NULL AND u.isBanned = false AND u.apTimeoutAt IS NULL '.
            'GROUP BY u.apInboxUrl';

        $res = $this->getEntityManager()->createQuery($dql)
            ->setParameter('magazine', $magazine)
            ->getResult();

        return array_map(fn ($item) => $item['apInboxUrl'], $res);
    }

    public function findWithoutKeys(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.privateKey IS NULL')
            ->andWhere('m.apId IS NULL')
            ->getQuery()
            ->getResult();
    }

    public function findByTag($tag): ?Magazine
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.tags IS NOT NULL AND JSONB_CONTAINS(m.tags, :tag) = true')
            ->orderBy('m.lastActive', 'DESC')
            ->setMaxResults(1)
            ->setParameter('tag', "\"$tag\"")
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByActivity()
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.postCount > 0')
            ->orWhere('m.entryCount > 0')
            ->andWhere('m.lastActive >= :date')
            ->andWhere('m.isAdult = false')
            ->andWhere('m.visibility = :visibility')
            ->setMaxResults(50)
            ->setParameter('date', new \DateTime('-5 months'))
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
            ->orderBy('m.entryCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByApGroupProfileId(array $apIds): ?Magazine
    {
        return $this->createQueryBuilder('m')
            ->where('m.apProfileId IN (?1)')
            ->setParameter(1, $apIds)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function search(string $magazine, int $page, int $perPage = self::PER_PAGE): Pagerfanta
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.visibility = :visibility')
            ->andWhere(
                'LOWER(m.name) LIKE LOWER(:q) OR LOWER(m.title) LIKE LOWER(:q) OR LOWER(m.description) LIKE LOWER(:q)'
            )
            ->orderBy('m.apId', 'DESC')
            ->orderBy('m.subscriptionsCount', 'DESC')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
            ->setParameter('q', '%'.$magazine.'%');

        $pagerfanta = new Pagerfanta(
            new QueryAdapter(
                $qb
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

    public function findRandom(?User $user = null): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $whereClauses = [];
        $parameters = [];
        if ($this->settingsManager->get('MBIN_SIDEBAR_SECTIONS_RANDOM_LOCAL_ONLY')) {
            $whereClauses[] = 'm.ap_id IS NULL';
        }
        if (null !== $user) {
            $subSql = 'SELECT * FROM magazine_block mb WHERE mb.magazine_id = m.id AND mb.user_id = :user';
            $whereClauses[] = "NOT EXISTS($subSql)";
            $parameters['user'] = [$user->getId(), ParameterType::INTEGER];
        }
        $whereString = SqlHelpers::makeWhereString($whereClauses);
        $sql = "SELECT m.id FROM magazine m $whereString ORDER BY random() LIMIT 5";
        $stmt = $conn->prepare($sql);
        foreach ($parameters as $param => $value) {
            $stmt->bindValue($param, $value[0], $value[1]);
        }
        $stmt = $stmt->executeQuery();
        $ids = $stmt->fetchAllAssociative();

        return $this->createQueryBuilder('m')
            ->where('m.id IN (:ids)')
            ->andWhere('m.isAdult = false')
            ->andWhere('m.visibility = :visibility')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function findRelated(string $magazine, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.entryCount > 0 OR m.postCount > 0')
            ->andWhere('m.title LIKE :magazine OR m.description LIKE :magazine OR m.name LIKE :magazine')
            ->andWhere('m.isAdult = false')
            ->andWhere('m.visibility = :visibility')
            ->setParameter('visibility', VisibilityInterface::VISIBILITY_VISIBLE)
            ->setParameter('magazine', "%{$magazine}%")
            ->setMaxResults(5);

        if (null !== $user) {
            $qb->andWhere($qb->expr()->not($qb->expr()->exists($this->sqlHelpers->getBlockedMagazinesDql($user))));
            $qb->setParameter('user', $user);
        }

        return $qb->getQuery()
            ->getResult();
    }

    public function findRemoteForUpdate(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.apId IS NOT NULL')
            ->andWhere('m.apDomain IS NULL')
            ->andWhere('m.apDeletedAt IS NULL')
            ->andWhere('m.apTimeoutAt IS NULL')
            ->addOrderBy('m.apFetchedAt', 'ASC')
            ->setMaxResults(1000)
            ->getQuery()
            ->getResult();
    }

    public function findForDeletionPaginated(int $page): PagerfantaInterface
    {
        $query = $this->createQueryBuilder('m')
            ->where('m.apId IS NULL')
            ->andWhere('m.visibility = :visibility')
            ->orderBy('m.markedForDeletionAt', 'ASC')
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

    public function findAbandoned(int $page = 1): PagerfantaInterface
    {
        $query = $this->createQueryBuilder('m')
            ->where('mod.magazine IS NOT NULL')
            ->andWhere('mod.isOwner = true')
            ->andWhere('u.lastActive < :date')
            ->andWhere('m.apId IS NULL')
            ->join('m.moderators', 'mod')
            ->join('mod.user', 'u')
            ->setParameter('date', new \DateTime('-1 month'))
            ->orderBy('u.lastActive', 'ASC')
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

    public function getMagazineFromModeratorsUrl($target): ?Magazine
    {
        if ($this->settingsManager->isLocalUrl($target)) {
            $matches = [];
            if (preg_match_all("/\/m\/([a-zA-Z0-9\-_:]+)\/moderators/", $target, $matches)) {
                $magName = $matches[1][0];

                return $this->findOneByName($magName);
            }
        } else {
            return $this->findOneBy(['apAttributedToUrl' => $target]);
        }

        return null;
    }

    public function getMagazineFromPinnedUrl($target): ?Magazine
    {
        if ($this->settingsManager->isLocalUrl($target)) {
            $matches = [];
            if (preg_match_all("/\/m\/([a-zA-Z0-9\-_:]+)\/pinned/", $target, $matches)) {
                $magName = $matches[1][0];

                return $this->findOneByName($magName);
            }
        } else {
            return $this->findOneBy(['apFeaturedUrl' => $target]);
        }

        return null;
    }
}
