<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Magazine;
use App\Entity\Moderator;
use App\Entity\User;
use App\Pagination\NativeQueryAdapter;
use App\Pagination\Transformation\ContentPopulationTransformer;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Psr\Log\LoggerInterface;

class SearchRepository
{
    public const PER_PAGE = 25;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentPopulationTransformer $transformer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function countModerated(User $user): int
    {
        $dql =
            'SELECT m FROM '.Magazine::class.' m WHERE m IN ('.
            'SELECT IDENTITY(md.magazine) FROM '.Moderator::class.' md WHERE md.user = :user) ORDER BY m.apId DESC, m.lastActive DESC';

        return \count(
            $this->entityManager->createQuery($dql)
                ->setParameter('user', $user)
                ->getResult()
        );
    }

    public function countBoosts(User $user): int
    {
        $conn = $this->entityManager->getConnection();
        $sql = "SELECT COUNT(*) as cnt FROM (
        SELECT entry_id as id, created_at, 'entry' AS type FROM entry_vote WHERE user_id = :userId AND choice = 1
        UNION ALL
        SELECT comment_id as id, created_at, 'entry_comment' AS type FROM entry_comment_vote WHERE user_id = :userId AND choice = 1
        UNION ALL
        SELECT post_id as id, created_at, 'post' AS type FROM post_vote WHERE user_id = :userId AND choice = 1
        UNION ALL
        SELECT comment_id as id, created_at, 'post_comment' AS type FROM post_comment_vote WHERE user_id = :userId AND choice = 1
        ) sub";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('userId', $user->getId());
        $stmt = $stmt->executeQuery();

        return $stmt->fetchAllAssociative()[0]['cnt'];
    }

    public function findBoosts(int $page, User $user): PagerfantaInterface
    {
        $conn = $this->entityManager->getConnection();
        $sql = "
        SELECT entry_id as id, created_at, 'entry' AS type FROM entry_vote WHERE user_id = :userId AND choice = 1
        UNION ALL
        SELECT comment_id as id, created_at, 'entry_comment' AS type FROM entry_comment_vote WHERE user_id = :userId AND choice = 1
        UNION ALL
        SELECT post_id as id, created_at, 'post' AS type FROM post_vote WHERE user_id = :userId AND choice = 1
        UNION ALL
        SELECT comment_id as id, created_at, 'post_comment' AS type FROM post_comment_vote WHERE user_id = :userId AND choice = 1
        ORDER BY created_at DESC";

        $pagerfanta = new Pagerfanta(new NativeQueryAdapter($conn, $sql, [
            'userId' => $user->getId(),
        ], transformer: $this->transformer));

        $pagerfanta->setCurrentPage($page);

        return $pagerfanta;
    }

    /**
     * @param 'entry'|'post'|'magazine'|'user'|'users+magazines'|'entry+post'|null $specificType
     */
    public function search(
        ?User $searchingUser,
        string $query,
        int $page = 1,
        ?int $authorId = null,
        ?int $magazineId = null,
        ?string $specificType = null,
        ?\DateTimeImmutable $sinceDate = null,
    ): PagerfantaInterface {
        $authorWhere = null !== $authorId ? 'AND e.user_id = :authorId' : '';
        $magazineWhere = null !== $magazineId ? 'AND e.magazine_id = :magazineId' : '';
        $createdWhere = null !== $sinceDate ? 'AND e.created_at >= :since' : '';
        $createdWhereMagazine = null !== $sinceDate ? 'AND m.created_at >= :since' : '';
        $createdWhereUser = null !== $sinceDate ? 'AND u.created_at >= :since' : '';
        $blockMagazineAndUserResult = null !== $authorId || null !== $magazineId ? 'AND false' : '';
        $conn = $this->entityManager->getConnection();
        $sqlEntry = "SELECT e.id, e.created_at, e.visibility, 2 * ts_rank_cd(e.title_ts, plainto_tsquery(:query)) + ts_rank_cd(e.body_ts, plainto_tsquery(:query)) as rank, 'entry' AS type FROM entry e
            INNER JOIN public.user u ON u.id = user_id
            INNER JOIN magazine m ON e.magazine_id = m.id
            WHERE (e.body_ts @@ plainto_tsquery( :query ) = true OR e.title_ts @@ plainto_tsquery( :query ) = true OR e.title LIKE :likeQuery)
                AND e.visibility = :visibility
                AND u.is_deleted = false
                AND u.ap_discoverable = true
                AND m.ap_discoverable = true
                AND NOT EXISTS (SELECT id FROM user_block ub WHERE ub.blocked_id = u.id AND ub.blocker_id = :queryingUser)
                AND NOT EXISTS (SELECT id FROM magazine_block mb WHERE mb.magazine_id = m.id AND mb.user_id = :queryingUser)
                AND NOT EXISTS (SELECT hl.id FROM hashtag_link hl INNER JOIN hashtag h ON h.id = hl.hashtag_id AND h.banned = true WHERE hl.entry_id = e.id)
                $authorWhere $magazineWhere $createdWhere
        UNION ALL
        SELECT e.id, e.created_at, e.visibility, 3 * ts_rank_cd(e.body_ts, plainto_tsquery(:query)) as rank, 'entry_comment' AS type FROM entry_comment e
            INNER JOIN public.user u ON u.id = user_id
            INNER JOIN magazine m ON e.magazine_id = m.id
            WHERE (e.body_ts @@ plainto_tsquery( :query ) = true)
                AND e.visibility = :visibility
                AND u.is_deleted = false
                AND u.ap_discoverable = true
                AND m.ap_discoverable = true
                AND NOT EXISTS (SELECT id FROM user_block ub WHERE ub.blocked_id = u.id AND ub.blocker_id = :queryingUser)
                AND NOT EXISTS (SELECT id FROM magazine_block mb WHERE mb.magazine_id = m.id AND mb.user_id = :queryingUser)
                AND NOT EXISTS (SELECT hl.id FROM hashtag_link hl INNER JOIN hashtag h ON h.id = hl.hashtag_id AND h.banned = true WHERE hl.entry_comment_id = e.id)
                $authorWhere $magazineWhere $createdWhere
        ";
        $sqlPost = "SELECT e.id, e.created_at, e.visibility, 3 * ts_rank_cd(e.body_ts, plainto_tsquery(:query)) as rank, 'post' AS type FROM post e
            INNER JOIN public.user u ON u.id = user_id
            INNER JOIN magazine m ON e.magazine_id = m.id
            WHERE (e.body_ts @@ plainto_tsquery( :query ) = true)
                AND e.visibility = :visibility
                AND u.is_deleted = false
                AND u.ap_discoverable = true
                AND m.ap_discoverable = true
                AND NOT EXISTS (SELECT id FROM user_block ub WHERE ub.blocked_id = u.id AND ub.blocker_id = :queryingUser)
                AND NOT EXISTS (SELECT id FROM magazine_block mb WHERE mb.magazine_id = m.id AND mb.user_id = :queryingUser)
                AND NOT EXISTS (SELECT hl.id FROM hashtag_link hl INNER JOIN hashtag h ON h.id = hl.hashtag_id AND h.banned = true WHERE hl.post_id = e.id)
                $authorWhere $magazineWhere $createdWhere
        UNION ALL
        SELECT e.id, e.created_at, e.visibility, 3 * ts_rank_cd(e.body_ts, plainto_tsquery(:query)) as rank, 'post_comment' AS type FROM post_comment e
            INNER JOIN public.user u ON u.id = user_id
            INNER JOIN magazine m ON e.magazine_id = m.id
            WHERE (e.body_ts @@ plainto_tsquery( :query ) = true)
                AND e.visibility = :visibility
                AND u.is_deleted = false
                AND u.ap_discoverable = true
                AND m.ap_discoverable = true
                AND NOT EXISTS (SELECT id FROM user_block ub WHERE ub.blocked_id = u.id AND ub.blocker_id = :queryingUser)
                AND NOT EXISTS (SELECT id FROM magazine_block mb WHERE mb.magazine_id = m.id AND mb.user_id = :queryingUser)
                AND NOT EXISTS (SELECT hl.id FROM hashtag_link hl INNER JOIN hashtag h ON h.id = hl.hashtag_id AND h.banned = true WHERE hl.post_comment_id = e.id)
                $authorWhere $magazineWhere $createdWhere
        ";

        $sqlMagazine = "SELECT m.Id, m.created_at, m.visibility, ts_rank_cd(m.name_ts, plainto_tsquery(:query)) + ts_rank_cd(m.title_ts, plainto_tsquery(:query)) + ts_rank_cd(m.description_ts, plainto_tsquery(:query)) as rank, 'magazine' AS type FROM magazine m
            WHERE (m.name_ts @@ plainto_tsquery( :query ) = true OR m.title_ts @@ plainto_tsquery( :query ) = true OR m.description_ts @@ plainto_tsquery( :query ) = true OR m.title LIKE :likeQuery)
                AND m.visibility = :visibility
                AND m.ap_deleted_at IS NULL
                AND m.marked_for_deletion_at IS NULL
                AND m.ap_discoverable = true
                AND NOT EXISTS (SELECT id FROM magazine_block mb WHERE mb.magazine_id = m.id AND mb.user_id = :queryingUser)
                $createdWhereMagazine $blockMagazineAndUserResult
        ";

        $sqlUser = "SELECT u.Id, u.created_at, u.visibility, ts_rank_cd(u.username_ts, plainto_tsquery(:query)) + ts_rank_cd(u.about_ts, plainto_tsquery(:query)) as rank, 'user' AS type FROM \"user\" u
            WHERE (u.username_ts @@ plainto_tsquery( :query ) = true OR u.about_ts @@ plainto_tsquery( :query ) = true OR u.username LIKE :likeQuery)
                AND u.visibility = :visibility
                AND u.is_deleted = false
                AND u.marked_for_deletion_at IS NULL
                AND u.ap_deleted_at IS NULL
                AND u.ap_discoverable = true
                AND NOT EXISTS (SELECT id FROM user_block ub WHERE ub.blocked_id = u.id AND ub.blocker_id = :queryingUser)
                $createdWhereUser $blockMagazineAndUserResult
        ";

        if (null === $specificType) {
            $sql = "$sqlEntry UNION ALL $sqlPost UNION ALL $sqlMagazine UNION ALL $sqlUser ORDER BY rank DESC, created_at DESC";
        } else {
            $sql = match ($specificType) {
                'entry' => "$sqlEntry ORDER BY rank DESC, created_at DESC",
                'post' => "$sqlPost ORDER BY rank DESC, created_at DESC",
                'magazine' => "$sqlMagazine ORDER BY rank DESC, created_at DESC",
                'user' => "$sqlUser ORDER BY rank DESC, created_at DESC",
                'users+magazines' => "$sqlMagazine UNION ALL $sqlUser ORDER BY rank DESC, created_at DESC",
                'entry+post' => "$sqlEntry UNION ALL $sqlPost ORDER BY rank DESC, created_at DESC",
                default => throw new \LogicException($specificType.' is not supported'),
            };
        }

        $parameters = [
            'query' => $query,
            'likeQuery' => "%$query%",
            'visibility' => VisibilityInterface::VISIBILITY_VISIBLE,
            'queryingUser' => $searchingUser?->getId() ?? -1,
        ];

        $this->logger->debug('Search query: {sql}', ['sql' => $sql]);

        if (null !== $authorId) {
            $parameters['authorId'] = $authorId;
        }

        if (null !== $magazineId) {
            $parameters['magazineId'] = $magazineId;
        }

        if (null !== $sinceDate) {
            $parameters['since'] = $sinceDate;
        }

        $adapter = new NativeQueryAdapter($conn, $sql, $parameters, transformer: $this->transformer);

        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setCurrentPage($page);

        return $pagerfanta;
    }

    public function findByApId($url): array
    {
        $conn = $this->entityManager->getConnection();
        $sql = "
        SELECT id, created_at, 'entry' AS type FROM entry WHERE ap_id = :url
        UNION ALL
        SELECT id, created_at, 'entry_comment' AS type FROM entry_comment WHERE ap_id = :url
        UNION ALL
        SELECT id, created_at, 'post' AS type FROM post WHERE ap_id = :url
        UNION ALL
        SELECT id, created_at, 'post_comment' AS type FROM post_comment WHERE ap_id = :url
        UNION ALL
        SELECT id, created_at, 'user' AS type FROM \"user\" WHERE ap_profile_id = :url
        UNION ALL
        SELECT id, created_at, 'magazine' AS type FROM magazine WHERE ap_profile_id = :url
        ORDER BY created_at DESC
        ";

        $pagerfanta = new Pagerfanta(new NativeQueryAdapter($conn, $sql, [
            'url' => "$url",
        ], transformer: $this->transformer));

        return $pagerfanta->getCurrentPageResults();
    }
}
