<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Entry;
use App\Entity\Post;
use App\Entity\User;
use App\Pagination\Cursor\CursorPagination;
use App\Pagination\Cursor\CursorPaginationInterface;
use App\Pagination\Cursor\NativeQueryCursorAdapter;
use App\Pagination\NativeQueryAdapter;
use App\Pagination\Pagerfanta;
use App\Pagination\Transformation\ContentPopulationTransformer;
use App\Utils\SqlHelpers;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\PagerfantaInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Cache\CacheInterface;

class ContentRepository
{
    public const int PER_PAGE = 25;

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
        $query = $this->getQueryAndParameters($criteria, false);
        $conn = $this->entityManager->getConnection();

        $numResults = null;
        if ('test' !== $this->kernel->getEnvironment() && !$criteria->magazine && !$criteria->moderated && !$criteria->favourite && Criteria::TIME_ALL === $criteria->time && Criteria::AP_ALL === $criteria->federation && 'all' === $criteria->type) {
            // pre-set the results to 1000 pages for queries not very limited by the parameters so the count query is not being executed
            $numResults = 1000 * ($criteria->perPage ?? self::PER_PAGE);
        }
        $fanta = new Pagerfanta(new NativeQueryAdapter($conn, $query['sql'], $query['parameters'], numOfResults: $numResults, transformer: $this->contentPopulationTransformer, cache: $this->cache));
        $fanta->setMaxPerPage($criteria->perPage ?? self::PER_PAGE);
        $fanta->setCurrentPage($criteria->page);

        return $fanta;
    }

    /**
     * @template-covariant TCursor
     *
     * @param TCursor|null $currentCursor
     *
     * @return CursorPaginationInterface<Entry|Post,TCursor>
     *
     * @throws Exception
     */
    public function findByCriteriaCursored(Criteria $criteria, mixed $currentCursor): CursorPaginationInterface
    {
        $query = $this->getQueryAndParameters($criteria, true);
        $conn = $this->entityManager->getConnection();
        $orderings = $this->getOrderings($criteria);

        $fanta = new CursorPagination(
            new NativeQueryCursorAdapter(
                $conn,
                $query['sql'],
                $this->getCursorWhereFromCriteria($criteria),
                $this->getCursorWhereInvertedFromCriteria($criteria),
                join(',', $orderings),
                join(',', SqlHelpers::invertOrderings($orderings)),
                $query['parameters'],
                transformer: $this->contentPopulationTransformer,
            ),
            $this->getCursorFieldFromCriteria($criteria),
            $criteria->perPage ?? self::PER_PAGE,
        );
        $fanta->setCurrentPage($currentCursor ?? $this->guessInitialCursor($criteria));

        return $fanta;
    }

    /**
     * @return array{sql: string, parameters: array}>
     */
    private function getQueryAndParameters(Criteria $criteria, bool $addCursor): array
    {
        $includeEntries = Criteria::CONTENT_COMBINED === $criteria->content || Criteria::CONTENT_THREADS === $criteria->content;
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
        if (Criteria::CONTENT_COMBINED !== $criteria->content) {
            if (Criteria::CONTENT_THREADS === $criteria->content) {
                $contentClausePost = 'false';
            } elseif (Criteria::CONTENT_MICROBLOG === $criteria->content) {
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
                    ' OR EXISTS (SELECT * FROM user_follow uf WHERE uf.following_id = :loggedInUser AND uf.follower_id = c.user_id)' :
                    ' OR c.user_id IN (:cachedUserFollows)');
            $subClauseEntry = $subClausePost
                .(null === $criteria->cachedUserSubscribedDomains ?
                    ' OR EXISTS (SELECT * FROM domain_subscription ds WHERE ds.domain_id = c.domain_id AND ds.user_id = :loggedInUser)' :
                    ' OR c.domain_id IN (:cachedUserSubscribedDomains)');

            if (null !== $criteria->cachedUserSubscribedMagazines) {
                $parameters['cachedUserSubscribedMagazines'] = $criteria->cachedUserSubscribedMagazines;
            }
            if (null !== $criteria->cachedUserSubscribedDomains && $includeEntries) {
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

        $allClause = '';
        $allClauseU = '';
        if (!$criteria->moderated && !$criteria->subscribed && !$criteria->magazine && !$criteria->user && !$criteria->domain && !$criteria->tag) {
            // hide all posts from non-discoverable users and magazines from /all (and only from there)
            $allClause = 'm.ap_discoverable = true OR m.ap_discoverable IS NULL';
            $allClauseU = 'u.ap_discoverable = true OR u.ap_discoverable IS NULL';
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
                $blockingClausePost = 'NOT EXISTS (SELECT * FROM user_block ub WHERE ub.blocker_id = :loggedInUser AND ub.blocked_id = c.user_id)';
            } else {
                $blockingClausePost = 'c.user_id NOT IN (:cachedUserBlocks)';
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
                $blockingClauseEntry = $blockingClausePost.' AND (c.domain_id IS NULL OR c.domain_id NOT IN (:cachedUserBlockedDomains))';
                if ($includeEntries) {
                    $parameters['cachedUserBlockedDomains'] = $criteria->cachedUserBlockedDomains;
                }
            }
        }

        $hideAdultClause = '';
        if ($user && $user->hideAdult) {
            $hideAdultClause = 'c.is_adult = FALSE AND m.is_adult = FALSE';
        }

        $visibilityClauseM = 'm.visibility = :visible';
        if (null === $criteria->cachedUserFollows) {
            $visibilityClauseC = 'c.visibility = :visible OR (c.visibility = :private AND EXISTS (SELECT * FROM user_follow uf WHERE uf.following_id = :loggedInUser AND uf.follower_id = c.user_id))';
        } else {
            $visibilityClauseC = 'c.visibility = :visible OR (c.visibility = :private AND c.user_id IN (:cachedUserFollows))';
        }

        $deletedClause = 'u.is_deleted = false';
        $visibilityClauseU = 'u.visibility = :visible';

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
            $visibilityClauseC,
            $allClause,
            $addCursor ? 'c.%cursor%' : '',
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
            $visibilityClauseC,
            $allClause,
            $addCursor ? 'c.%cursor%' : '',
        ]);

        $outerWhere = SqlHelpers::makeWhereString([
            $visibilityClauseU,
            $deletedClause,
            $allClauseU,
            $addCursor ? 'content.%cursor%' : '',
        ]);

        $orderings = $addCursor ? ['%cursorSort%'] : $this->getOrderings($criteria);

        $orderBy = 'ORDER BY '.join(', ', $orderings);
        // only join domain if we are explicitly looking at one
        $domainJoin = $criteria->domain ? 'LEFT JOIN domain d ON d.id = c.domain_id' : '';

        $entrySql = "SELECT c.id, 'entry' as type, c.type as content_type, c.created_at, c.ranking, c.score, c.comment_count, c.sticky, c.last_active, c.user_id FROM entry c
            LEFT JOIN magazine m ON c.magazine_id = m.id
            $domainJoin
            $entryWhere";
        $postSql = "SELECT c.id, 'post' as type, 'microblog' as content_type, c.created_at, c.ranking, c.score, c.comment_count, c.sticky, c.last_active, c.user_id FROM post c
            LEFT JOIN magazine m ON c.magazine_id = m.id
            $postWhere";

        $innerLimit = $addCursor ? 'LIMIT :limit' : '';
        $innerSql = '';
        if (Criteria::CONTENT_THREADS === $criteria->content) {
            $innerSql = "$entrySql $orderBy $innerLimit";
        } elseif (Criteria::CONTENT_MICROBLOG === $criteria->content) {
            $innerSql = "$postSql $orderBy $innerLimit";
        } else {
            $innerSql = "($entrySql $orderBy $innerLimit) UNION ALL ($postSql $orderBy $innerLimit)";
        }

        $sql = "SELECT content.* FROM ($innerSql) content
            INNER JOIN \"user\" u ON content.user_id = u.id
            $outerWhere
            $orderBy";

        if (!str_contains($sql, ':loggedInUser')) {
            $parameters = array_filter($parameters, fn ($key) => 'loggedInUser' !== $key, mode: ARRAY_FILTER_USE_KEY);
        }

        $rewritten = SqlHelpers::rewriteArrayParameters($parameters, $sql);

        $this->logger->debug('{s} | {p}', ['s' => $sql, 'p' => $parameters]);
        $this->logger->debug('Rewritten to: {s} | {p}', ['p' => $rewritten['parameters'], 's' => $rewritten['sql']]);

        return $rewritten;
    }

    private function getCursorFieldFromCriteria(Criteria $criteria): string
    {
        return match ($criteria->sortOption) {
            Criteria::SORT_TOP => 'score',
            Criteria::SORT_HOT => 'ranking',
            Criteria::SORT_COMMENTED => 'commentCount',
            Criteria::SORT_ACTIVE => 'lastActive',
            default => 'createdAt',
        };
    }

    private function getCursorWhereFromCriteria(Criteria $criteria): string
    {
        return match ($criteria->sortOption) {
            Criteria::SORT_TOP => 'score < :cursor',
            Criteria::SORT_HOT => 'ranking < :cursor',
            Criteria::SORT_COMMENTED => 'comment_count < :cursor',
            Criteria::SORT_ACTIVE => 'last_active < :cursor',
            Criteria::SORT_OLD => 'created_at > :cursor',
            default => 'created_at < :cursor',
        };
    }

    private function getCursorWhereInvertedFromCriteria(Criteria $criteria): string
    {
        return match ($criteria->sortOption) {
            Criteria::SORT_TOP => 'score >= :cursor',
            Criteria::SORT_HOT => 'ranking >= :cursor',
            Criteria::SORT_COMMENTED => 'comment_count >= :cursor',
            Criteria::SORT_ACTIVE => 'last_active >= :cursor',
            Criteria::SORT_OLD => 'created_at <= :cursor',
            default => 'created_at >= :cursor',
        };
    }

    public function guessInitialCursor(Criteria $criteria): mixed
    {
        return match ($criteria->sortOption) {
            Criteria::SORT_TOP, Criteria::SORT_HOT, Criteria::SORT_COMMENTED => 2147483647, // postgresql max int
            Criteria::SORT_OLD => (new \DateTimeImmutable())->setTimestamp(0),
            default => new \DateTimeImmutable('now + 1 minute'),
        };
    }

    private function getOrderings(Criteria $criteria): array
    {
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

        return $orderings;
    }
}
