<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\User;
use App\Pagination\NativeQueryAdapter;
use App\Pagination\Pagerfanta;
use App\Pagination\Transformation\ContentPopulationTransformer;
use App\Utils\SqlHelpers;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\PagerfantaInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ContentRepository
{
    public const int PER_PAGE = 25;

    public const string USER_FOLLOWS_KEY = 'cached_user_follows_';
    public const string USER_MAGAZINE_SUBSCRIPTION_KEY = 'cached_user_magazine_subscription_';
    public const string USER_MAGAZINE_MODERATION_KEY = 'cached_user_magazine_moderation_';
    public const string USER_DOMAIN_SUBSCRIPTION_KEY = 'cached_user_domain_subscription_';
    public const string USER_BLOCKS_KEY = 'cached_user_blocks_';
    public const string USER_MAGAZINE_BLOCKS_KEY = 'cached_user_magazine_block_';
    public const string USER_DOMAIN_BLOCKS_KEY = 'cached_user_domain_block_';

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentPopulationTransformer $contentPopulationTransformer,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly KernelInterface $kernel,
    ) {
    }

    public function findByCriteria(Criteria $criteria): PagerfantaInterface
    {
        $parameters = [
            'visible' => VisibilityInterface::VISIBILITY_VISIBLE,
            'private' => VisibilityInterface::VISIBILITY_PRIVATE,
        ];

        /** @var ?User $user */
        $user = $this->security->getUser();
        $parameters['loggedInUser'] = $user?->getId();

        $timeClause = '';
        if ($criteria->time && Criteria::TIME_ALL !== $criteria->time) {
            $timeClause = 'c.created_at >= :time';
            $parameters['time'] = $criteria->getSince();
        }

        $magazineClause = '';
        if ($criteria->magazine) {
            $magazineClause = 'c.magazine_id = :magazine';
            $parameters['magazine'] = $criteria->magazine->getId();
        }

        $userClause = '';
        if ($criteria->user) {
            $userClause = 'c.user = :user';
            $parameters['user'] = $criteria->user->getId();
        }

        $hashtagClauseEntry = '';
        $hashtagClausePost = '';
        if ($criteria->tag) {
            $hashtagClauseEntry = 'EXISTS (SELECT * FROM hashtag_link hl INNER JOIN hashtag h ON hl.hashtag_id = h.id WHERE hl.entry_id = c.id AND h.tag = :hashtag)';
            $hashtagClausePost = 'EXISTS (SELECT * FROM hashtag_link hl INNER JOIN hashtag h ON hl.hashtag_id = h.id WHERE hl.post_id = c.id AND h.tag = :hashtag)';
            $parameters['hashtag'] = $criteria->tag;
        }

        $federationClause = '';
        if (Criteria::AP_LOCAL === $criteria->federation) {
            $federationClause = 'c.ap_id IS NULL';
        } elseif (Criteria::AP_FEDERATED === $criteria->federation) {
            $federationClause = 'c.ap_id IS NOT NULL';
        }

        $domainClausePost = '';
        $domainClauseEntry = '';
        if ($criteria->domain) {
            $domainClauseEntry = 'd.name = :domain';
            $parameters['domain'] = $criteria->domain;
            $domainClausePost = 'false';
        }

        $languagesClause = '';
        if ($criteria->languages) {
            $languagesClause = 'c.lang IN (:languages)';
            $parameters['languages'] = $criteria->languages;
        }

        $contentTypeClauseEntry = '';
        $contentTypeClausePost = '';
        if ($criteria->type && 'all' !== $criteria->type) {
            $contentTypeClauseEntry = 'c.type = :type';
            $contentTypeClausePost = 'false';
            $parameters['type'] = $criteria->type;
        }

        $contentClauseEntry = '';
        $contentClausePost = '';
        if ('all' !== $criteria->content) {
            if ('threads' === $criteria->content) {
                $contentClausePost = 'false';
            } elseif ('microblog' === $criteria->content) {
                $contentClauseEntry = 'false';
            } else {
                throw new \LogicException("cannot handle content of type $criteria->content");
            }
        }

        if (null !== $criteria->cachedUserFollows) {
            $parameters['cachedUserFollows'] = $criteria->cachedUserFollows;
        }

        $subClausePost = '';
        $subClauseEntry = '';
        if ($user && $criteria->subscribed) {
            $subClausePost = 'c.user_id = :loggedInUser'
                .(null === $criteria->cachedUserSubscribedMagazines ?
                    ' OR EXISTS (SELECT * FROM magazine_subscription ms WHERE ms.user_id = :loggedInUser AND ms.magazine_id = m.id)' :
                    ' OR m.id IN (:cachedUserSubscribedMagazines)')
                .(null === $criteria->cachedUserFollows ?
                    ' OR EXISTS (SELECT * FROM user_follow uf WHERE uf.following_id = :loggedInUser AND uf.follower_id = u.id)' :
                    ' OR u.id IN (:cachedUserFollows)');
            $subClauseEntry = $subClausePost
                .(null === $criteria->cachedUserSubscribedDomains ?
                    ' OR EXISTS (SELECT * FROM domain_subscription ds WHERE ds.domain_id = c.domain_id AND ds.user_id = :loggedInUser)' :
                    ' OR d.id IN (:cachedUserSubscribedDomains)');

            if (null !== $criteria->cachedUserSubscribedMagazines) {
                $parameters['cachedUserSubscribedMagazines'] = $criteria->cachedUserSubscribedMagazines;
            }
            if (null !== $criteria->cachedUserSubscribedDomains) {
                $parameters['cachedUserSubscribedDomains'] = $criteria->cachedUserSubscribedDomains;
            }
        }

        $modClause = '';
        if ($user && $criteria->moderated) {
            if (null === $criteria->cachedUserModeratedMagazines) {
                $modClause = 'EXISTS (SELECT * FROM moderator mod WHERE mod.magazine_id = m.id AND mod.user_id = :loggedInUser)';
            } else {
                $modClause = 'm.id IN (:cachedUserModeratedMagazines)';
                $parameters['cachedUserModeratedMagazines'] = $criteria->cachedUserModeratedMagazines;
            }
        }

        $favClauseEntry = '';
        $favClausePost = '';
        if ($user && $criteria->favourite) {
            $favClauseEntry = 'EXISTS (SELECT * FROM favourite f WHERE f.entry_id = c.id AND f.user_id = :loggedInUser)';
            $favClausePost = 'EXISTS (SELECT * FROM favourite f WHERE f.post_id = c.id AND f.user_id = :loggedInUser)';
        }

        $blockingClausePost = '';
        $blockingClauseEntry = '';
        if ($user && (!$criteria->magazine || !$criteria->magazine->userIsModerator($user)) && !$criteria->moderated) {
            if (null === $criteria->cachedUserBlocks) {
                $blockingClausePost = 'NOT EXISTS (SELECT * FROM user_block ub WHERE ub.blocker_id = :loggedInUser AND ub.blocked_id = u.id)';
            } else {
                $blockingClausePost = 'u.id NOT IN (:cachedUserBlocks)';
                $parameters['cachedUserBlocks'] = $criteria->cachedUserBlocks;
            }

            if (!$criteria->domain) {
                if (null === $criteria->cachedUserBlockedMagazines) {
                    $blockingClausePost .= ' AND NOT EXISTS (SELECT * FROM magazine_block mb WHERE mb.magazine_id = m.id AND mb.user_id = :loggedInUser)';
                } else {
                    $blockingClausePost .= ' AND (m IS NULL OR m.id NOT IN (:cachedUserBlockedMagazines))';
                    $parameters['cachedUserBlockedMagazines'] = $criteria->cachedUserBlockedMagazines;
                }
            }

            if (null === $criteria->cachedUserBlockedDomains) {
                $blockingClauseEntry = $blockingClausePost.' AND NOT EXISTS (SELECT * FROM domain_block db WHERE db.user_id = :loggedInUser AND db.domain_id = c.domain_id)';
            } else {
                $blockingClauseEntry = $blockingClausePost.' AND (d IS NULL OR d.id NOT IN (:cachedUserBlockedDomains))';
                $parameters['cachedUserBlockedDomains'] = $criteria->cachedUserBlockedDomains;
            }
        }

        $hideAdultClause = '';
        if ($user && $user->hideAdult) {
            $hideAdultClause = 'c.is_adult = FALSE AND m.is_adult = FALSE';
        }

        $visibilityClauseM = 'm.visibility = :visible';
        $visibilityClauseU = 'u.visibility = :visible';
        if (null === $criteria->cachedUserFollows) {
            $visibilityClauseC = 'c.visibility = :visible OR (c.visibility = :private AND EXISTS (SELECT * FROM user_follow uf WHERE uf.following_id = :loggedInUser AND uf.follower_id = u.id))';
        } else {
            $visibilityClauseC = 'c.visibility = :visible OR (c.visibility = :private AND u.id IN (:cachedUserFollows))';
        }
        $deletedClause = 'u.is_deleted = false';

        $entryWhere = SqlHelpers::makeWhereString([
            $contentClauseEntry,
            $timeClause,
            $magazineClause,
            $userClause,
            $hashtagClauseEntry,
            $federationClause,
            $domainClauseEntry,
            $languagesClause,
            $contentTypeClauseEntry,
            $subClauseEntry,
            $modClause,
            $favClauseEntry,
            $blockingClauseEntry,
            $hideAdultClause,
            $visibilityClauseM,
            $visibilityClauseU,
            $visibilityClauseC,
            $deletedClause,
        ]);

        $postWhere = SqlHelpers::makeWhereString([
            $contentClausePost,
            $timeClause,
            $magazineClause,
            $userClause,
            $hashtagClausePost,
            $federationClause,
            $domainClausePost,
            $languagesClause,
            $contentTypeClausePost,
            $subClausePost,
            $modClause,
            $favClausePost,
            $blockingClausePost,
            $hideAdultClause,
            $visibilityClauseM,
            $visibilityClauseU,
            $visibilityClauseC,
            $deletedClause,
        ]);

        $orderings = [];

        if ($criteria->stickiesFirst) {
            $orderings[] = 'sticky DESC';
        }

        switch ($criteria->sortOption) {
            case Criteria::SORT_TOP:
                $orderings[] = 'score DESC';
                break;
            case Criteria::SORT_HOT:
                $orderings[] = 'ranking DESC';
                break;
            case Criteria::SORT_COMMENTED:
                $orderings[] = 'comment_count DESC';
                break;
            case Criteria::SORT_ACTIVE:
                $orderings[] = 'last_active DESC';
                break;
            default:
        }

        switch ($criteria->sortOption) {
            case Criteria::SORT_OLD:
                $orderings[] = 'created_at ASC';
                break;
            case Criteria::SORT_NEW:
            default:
                $orderings[] = 'created_at DESC';
        }

        $orderBy = 'ORDER BY '.join(', ', $orderings);

        $entrySql = "SELECT c.id, 'entry' as type, c.type as content_type, c.created_at, c.ranking, c.score, c.comment_count, c.sticky, c.last_active FROM entry c
            LEFT JOIN magazine m ON c.magazine_id = m.id
            LEFT JOIN domain d ON c.domain_id = d.id
            INNER JOIN \"user\" u ON c.user_id = u.id
            $entryWhere";
        $postSql = "SELECT c.id, 'post' as type, 'microblog' as content_type, c.created_at, c.ranking, c.score, c.comment_count, c.sticky, c.last_active FROM post c
            LEFT JOIN magazine m ON c.magazine_id = m.id
            INNER JOIN \"user\" u ON c.user_id = u.id
            $postWhere";

        $sql = "$entrySql UNION $postSql $orderBy";
        if (!str_contains($sql, ':loggedInUser')) {
            $parameters = array_filter($parameters, fn ($key) => 'loggedInUser' !== $key, mode: ARRAY_FILTER_USE_KEY);
        }

        $rewritten = SqlHelpers::rewriteArrayParameters($parameters, $sql);
        $conn = $this->entityManager->getConnection();

        $this->logger->debug('{s} | {p}', ['s' => $sql, 'p' => $parameters]);
        $this->logger->debug('Rewritten to: {s} | {p}', ['p' => $rewritten['parameters'], 's' => $rewritten['sql']]);

        $fanta = new Pagerfanta(new NativeQueryAdapter($conn, $rewritten['sql'], $rewritten['parameters'], transformer: $this->contentPopulationTransformer));
        $fanta->setMaxPerPage($criteria->perPage ?? self::PER_PAGE);
        $fanta->setCurrentPage($criteria->page);

        return $fanta;
    }

    /**
     * @return int[] the ids of the users $user follows
     *
     * @throws InvalidArgumentException|Exception
     */
    public function getCachedUserFollows(User $user): array
    {
        $sql = 'SELECT following_id FROM user_follow WHERE follower_id = :uId';
        if ('test' === $this->kernel->getEnvironment()) {
            return $this->fetchSingleColumnAsArray($sql, $user);
        }

        return $this->cache->get(self::USER_FOLLOWS_KEY.$user->getId(), function (ItemInterface $item) use ($user, $sql) {
            return $this->fetchSingleColumnAsArray($sql, $user);
        });
    }

    public function clearCachedUserFollows(User $user): void
    {
        $this->logger->debug('Clearing cached user follows for user {u}', ['u' => $user->username]);
        try {
            $this->cache->delete(self::USER_FOLLOWS_KEY.$user->getId());
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('There was an error clearing the cached user follows of user "{u}": {m}', ['u' => $user->username, 'm' => $exception->getMessage()]);
        }
    }

    /**
     * @return int[] the ids of the magazines $user is subscribed to
     *
     * @throws InvalidArgumentException|Exception
     */
    public function getCachedUserSubscribedMagazines(User $user): array
    {
        $sql = 'SELECT magazine_id FROM magazine_subscription WHERE user_id = :uId';
        if ('test' === $this->kernel->getEnvironment()) {
            return $this->fetchSingleColumnAsArray($sql, $user);
        }

        return $this->cache->get(self::USER_MAGAZINE_SUBSCRIPTION_KEY.$user->getId(), function (ItemInterface $item) use ($user, $sql) {
            return $this->fetchSingleColumnAsArray($sql, $user);
        });
    }

    public function clearCachedUserSubscribedMagazines(User $user): void
    {
        $this->logger->debug('Clearing cached magazine subscriptions for user {u}', ['u' => $user->username]);
        try {
            $this->cache->delete(self::USER_MAGAZINE_SUBSCRIPTION_KEY.$user->getId());
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('There was an error clearing the cached subscribed Magazines of user "{u}": {m}', ['u' => $user->username, 'm' => $exception->getMessage()]);
        }
    }

    /**
     * @return int[] the ids of the magazines $user moderates
     *
     * @throws InvalidArgumentException|Exception
     */
    public function getCachedUserModeratedMagazines(User $user): array
    {
        $sql = 'SELECT magazine_id FROM moderator WHERE user_id = :uId';
        if ('test' === $this->kernel->getEnvironment()) {
            return $this->fetchSingleColumnAsArray($sql, $user);
        }

        return $this->cache->get(self::USER_MAGAZINE_MODERATION_KEY.$user->getId(), function (ItemInterface $item) use ($user, $sql) {
            return $this->fetchSingleColumnAsArray($sql, $user);
        });
    }

    public function clearCachedUserModeratedMagazines(User $user): void
    {
        $this->logger->debug('Clearing cached moderated magazines for user {u}', ['u' => $user->username]);
        try {
            $this->cache->delete(self::USER_MAGAZINE_MODERATION_KEY.$user->getId());
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('There was an error clearing the cached moderated magazines of user "{u}": {m}', ['u' => $user->username, 'm' => $exception->getMessage()]);
        }
    }

    /**
     * @return int[] the ids of the domains $user is subscribed to
     *
     * @throws InvalidArgumentException|Exception
     */
    public function getCachedUserSubscribedDomains(User $user): array
    {
        $sql = 'SELECT domain_id FROM domain_subscription WHERE user_id = :uId';
        if ('test' === $this->kernel->getEnvironment()) {
            return $this->fetchSingleColumnAsArray($sql, $user);
        }

        return $this->cache->get(self::USER_DOMAIN_SUBSCRIPTION_KEY.$user->getId(), function (ItemInterface $item) use ($user, $sql) {
            return $this->fetchSingleColumnAsArray($sql, $user);
        });
    }

    public function clearCachedUserSubscribedDomains(User $user): void
    {
        $this->logger->debug('Clearing cached domain subscriptions for user {u}', ['u' => $user->username]);
        try {
            $this->cache->delete(self::USER_DOMAIN_SUBSCRIPTION_KEY.$user->getId());
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('There was an error clearing the cached subscribed domains of user "{u}": {m}', ['u' => $user->username, 'm' => $exception->getMessage()]);
        }
    }

    /**
     * @return int[] the ids of the domains $user is subscribed to
     *
     * @throws InvalidArgumentException|Exception
     */
    public function getCachedUserBlocks(User $user): array
    {
        $sql = 'SELECT blocked_id FROM user_block WHERE blocker_id = :uId';
        if ('test' === $this->kernel->getEnvironment()) {
            return $this->fetchSingleColumnAsArray($sql, $user);
        }

        return $this->cache->get(self::USER_BLOCKS_KEY.$user->getId(), function (ItemInterface $item) use ($user, $sql) {
            return $this->fetchSingleColumnAsArray($sql, $user);
        });
    }

    public function clearCachedUserBlocks(User $user): void
    {
        $this->logger->debug('Clearing cached user blocks for user {u}', ['u' => $user->username]);
        try {
            $this->cache->delete(self::USER_BLOCKS_KEY.$user->getId());
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('There was an error clearing the cached blocked user of user "{u}": {m}', ['u' => $user->username, 'm' => $exception->getMessage()]);
        }
    }

    /**
     * @return int[] the ids of the domains $user is subscribed to
     *
     * @throws InvalidArgumentException|Exception
     */
    public function getCachedUserMagazineBlocks(User $user): array
    {
        $sql = 'SELECT magazine_id FROM magazine_block WHERE user_id = :uId';
        if ('test' === $this->kernel->getEnvironment()) {
            return $this->fetchSingleColumnAsArray($sql, $user);
        }

        return $this->cache->get(self::USER_MAGAZINE_BLOCKS_KEY.$user->getId(), function (ItemInterface $item) use ($user, $sql) {
            return $this->fetchSingleColumnAsArray($sql, $user);
        });
    }

    public function clearCachedUserMagazineBlocks(User $user): void
    {
        $this->logger->debug('Clearing cached magazine blocks for user {u}', ['u' => $user->username]);
        try {
            $this->cache->delete(self::USER_MAGAZINE_BLOCKS_KEY.$user->getId());
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('There was an error clearing the cached blocked magazines of user "{u}": {m}', ['u' => $user->username, 'm' => $exception->getMessage()]);
        }
    }

    /**
     * @return int[] the ids of the domains $user is subscribed to
     *
     * @throws InvalidArgumentException|Exception
     */
    public function getCachedUserDomainBlocks(User $user): array
    {
        $sql = 'SELECT domain_id FROM domain_block WHERE user_id = :uId';
        if ('test' === $this->kernel->getEnvironment()) {
            return $this->fetchSingleColumnAsArray($sql, $user);
        }

        return $this->cache->get(self::USER_DOMAIN_BLOCKS_KEY.$user->getId(), function (ItemInterface $item) use ($user, $sql) {
            return $this->fetchSingleColumnAsArray($sql, $user);
        });
    }

    public function clearCachedUserDomainBlocks(User $user): void
    {
        $this->logger->debug('Clearing cached domain blocks for user {u}', ['u' => $user->username]);
        try {
            $this->cache->delete(self::USER_DOMAIN_BLOCKS_KEY.$user->getId());
        } catch (InvalidArgumentException $exception) {
            $this->logger->warning('There was an error clearing the cached blocked domains of user "{u}": {m}', ['u' => $user->username, 'm' => $exception->getMessage()]);
        }
    }

    /**
     * @param string $sql the sql to fetch the single column, should contain a 'uId' Parameter
     *
     * @return int[]
     *
     * @throws Exception
     */
    public function fetchSingleColumnAsArray(string $sql, User $user): array
    {
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['uId' => $user->getId()]);
        $rows = $result->fetchAllAssociative();
        $result = [];
        foreach ($rows as $row) {
            $result[] = $row[array_key_first($row)];
        }

        $this->logger->debug('Fetching single column row from {sql}: {res}', ['sql' => $sql, 'res' => $result]);

        return $result;
    }
}
