<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\User;
use App\Pagination\NativeQueryAdapter;
use App\Pagination\Pagerfanta;
use App\Pagination\Transformation\ContentPopulationTransformer;
use App\Utils\SqlHelpers;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\PagerfantaInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ContentRepository
{
    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentPopulationTransformer $contentPopulationTransformer,
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
        if ($user) {
            $parameters['loggedInUser'] = $user->getId();
        }

        $timeClause = '';
        if ($criteria->time && Criteria::TIME_ALL !== $criteria->time) {
            $timeClause = 'c.created_at >= :time';
            $parameters['time'] = $criteria->getSince();
        }

        $stickyClause = '';
        if (!$criteria->stickiesFirst) {
            $stickyClause = 'c.sticky = false';
        }

        $magazineClause = '';
        if ($criteria->magazine) {
            $magazineClause = 'c.magazine = :magazine';
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
            $domainClauseEntry = 'c.domain_id = :domain';
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

        $subClausePost = '';
        $subClauseEntry = '';
        if ($user && $criteria->subscribed) {
            $subClausePost = 'c.user_id = :loggedInUser
                OR EXISTS (SELECT * FROM magazine_subscription ms WHERE ms.user_id = :loggedInUser AND ms.magazine_id = m.id)
                OR EXISTS (SELECT * FROM user_follow uf WHERE uf.following_id = :loggedInUser AND uf.follower_id = u.id)';
            $subClauseEntry = $subClausePost.' OR EXISTS (SELECT * FROM domain_subscription ds WHERE ds.domain_id = c.domain_id AND ds.user_id = :loggedInUser)';
        }

        $modClause = '';
        if ($user && $criteria->moderated) {
            $modClause = 'EXISTS (SELECT * FROM moderator mod WHERE mod.magazine_id = m.id AND mod.user_id = :loggedInUser)';
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
            $blockingClausePost = 'NOT EXISTS (SELECT * FROM user_block ub WHERE ub.blocker_id = :loggedInUser AND ub.blocked_id = u.id)';
            if (!$criteria->domain) {
                $blockingClausePost .= ' AND NOT EXISTS (SELECT * FROM magazine_block mb WHERE mb.magazine_id = m.id AND mb.user_id = :loggedInUser)';
            }
            $blockingClauseEntry = $blockingClausePost.' AND NOT EXISTS (SELECT * FROM domain_block db WHERE db.user_id = :loggedInUser AND db.domain_id = c.domain_id)';
        }

        $hideAdultClause = '';
        if ($user && $user->hideAdult) {
            $hideAdultClause = 'c.is_adult = FALSE AND m.is_adult = FALSE';
        }

        $visibilityClauseM = 'm.visibility = :visible';
        $visibilityClauseU = 'u.visibility = :visible';
        $visibilityClauseC = 'c.visibility = :visible OR (c.visibility = :private AND EXISTS (SELECT * FROM user_follow uf WHERE uf.following_id = :loggedInUser AND uf.follower_id = u.id))';
        $deletedClause = 'u.is_deleted = false';

        $entryWhere = SqlHelpers::makeWhereString([
            $timeClause,
            $stickyClause,
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
            $timeClause,
            $stickyClause,
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

        $entrySql = "SELECT c.id, 'entry' as type, c.type as content_type, c.created_at, c.ranking, c.score, c.comment_count FROM entry c
            LEFT JOIN magazine m ON c.magazine_id = m.id
            INNER JOIN \"user\" u ON c.user_id = u.id
            $entryWhere";
        $postSql = "SELECT c.id, 'post' as type, 'microblog' as content_type, c.created_at, c.ranking, c.score, c.comment_count FROM post c
            LEFT JOIN magazine m ON c.magazine_id = m.id
            INNER JOIN \"user\" u ON c.user_id = u.id
            $postWhere";

        $sql = "$entrySql UNION $postSql $orderBy";
        $parameters = $this->rewriteArrayParameters($parameters);
        $conn = $this->entityManager->getConnection();

        $fanta = new Pagerfanta(new NativeQueryAdapter($conn, $sql, $parameters, transformer: $this->contentPopulationTransformer));
        $fanta->setMaxPerPage($criteria->perPage ?? 25);
        $fanta->setCurrentPage($criteria->page);

        return $fanta;
    }

    private function rewriteArrayParameters(array $parameters): array
    {
        $newParameters = [];
        foreach ($parameters as $name => $value) {
            if (\is_array($value)) {
                $size = \sizeof($value);
                for ($i = 0; $i < $size; ++$i) {
                    $newParameters["$name$i"] = $value[$i];
                }
            } else {
                $newParameters[$name] = $value;
            }
        }

        return $newParameters;
    }
}
