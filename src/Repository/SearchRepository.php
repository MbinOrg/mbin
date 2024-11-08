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

class SearchRepository
{
    public const PER_PAGE = 25;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ContentPopulationTransformer $transformer,
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

    public function search(?User $searchingUser, string $query, int $page = 1): PagerfantaInterface
    {
        $conn = $this->entityManager->getConnection();
        $sql = "SELECT e.id, e.created_at, e.visibility, 'entry' AS type FROM entry e
            INNER JOIN public.user u ON u.id = user_id
            INNER JOIN magazine m ON e.magazine_id = m.id
            WHERE (body_ts @@ plainto_tsquery( :query ) = true OR title_ts @@ plainto_tsquery( :query ) = true)
                AND e.visibility = :visibility
                AND u.is_deleted = false
                AND NOT EXISTS (SELECT id FROM user_block ub WHERE ub.blocked_id = u.id AND ub.blocker_id = :queryingUser)
                AND NOT EXISTS (SELECT id FROM magazine_block mb WHERE mb.magazine_id = m.id AND mb.user_id = :queryingUser)
                AND NOT EXISTS (SELECT hl.id FROM hashtag_link hl INNER JOIN hashtag h ON h.id = hl.hashtag_id AND h.banned = true WHERE hl.entry_id = e.id)
        UNION ALL
        SELECT e.id, e.created_at, e.visibility, 'entry_comment' AS type FROM entry_comment e
            INNER JOIN public.user u ON u.id = user_id
            INNER JOIN magazine m ON e.magazine_id = m.id
            WHERE body_ts @@ plainto_tsquery( :query ) = true
                AND e.visibility = :visibility
                AND u.is_deleted = false
                AND NOT EXISTS (SELECT id FROM user_block ub WHERE ub.blocked_id = u.id AND ub.blocker_id = :queryingUser)
                AND NOT EXISTS (SELECT id FROM magazine_block mb WHERE mb.magazine_id = m.id AND mb.user_id = :queryingUser)
                AND NOT EXISTS (SELECT hl.id FROM hashtag_link hl INNER JOIN hashtag h ON h.id = hl.hashtag_id AND h.banned = true WHERE hl.entry_comment_id = e.id)
        UNION ALL
        SELECT e.id, e.created_at, e.visibility, 'post' AS type FROM post e
            INNER JOIN public.user u ON u.id = user_id
            INNER JOIN magazine m ON e.magazine_id = m.id
            WHERE body_ts @@ plainto_tsquery( :query ) = true
                AND e.visibility = :visibility
                AND u.is_deleted = false
                AND NOT EXISTS (SELECT id FROM user_block ub WHERE ub.blocked_id = u.id AND ub.blocker_id = :queryingUser)
                AND NOT EXISTS (SELECT id FROM magazine_block mb WHERE mb.magazine_id = m.id AND mb.user_id = :queryingUser)
                AND NOT EXISTS (SELECT hl.id FROM hashtag_link hl INNER JOIN hashtag h ON h.id = hl.hashtag_id AND h.banned = true WHERE hl.post_id = e.id)
        UNION ALL
        SELECT e.id, e.created_at, e.visibility, 'post_comment' AS type FROM post_comment e
            INNER JOIN public.user u ON u.id = user_id
            INNER JOIN magazine m ON e.magazine_id = m.id
            WHERE body_ts @@ plainto_tsquery( :query ) = true
                AND e.visibility = :visibility
                AND u.is_deleted = false
                AND NOT EXISTS (SELECT id FROM user_block ub WHERE ub.blocked_id = u.id AND ub.blocker_id = :queryingUser)
                AND NOT EXISTS (SELECT id FROM magazine_block mb WHERE mb.magazine_id = m.id AND mb.user_id = :queryingUser)
                AND NOT EXISTS (SELECT hl.id FROM hashtag_link hl INNER JOIN hashtag h ON h.id = hl.hashtag_id AND h.banned = true WHERE hl.post_comment_id = e.id)
        ORDER BY created_at DESC";
        $adapter = new NativeQueryAdapter($conn, $sql, [
            'query' => $query,
            'visibility' => VisibilityInterface::VISIBILITY_VISIBLE,
            'queryingUser' => $searchingUser?->getId() ?? -1,
        ], transformer: $this->transformer);

        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setCurrentPage($page);

        return $pagerfanta;
    }

    public function findByApId($url): array
    {
        $conn = $this->entityManager->getConnection();
        $sql = "
        SELECT id, created_at, 'entry' AS type FROM entry WHERE ap_id ILIKE :url
        UNION ALL
        SELECT id, created_at, 'entry_comment' AS type FROM entry_comment WHERE ap_id ILIKE :url
        UNION ALL
        SELECT id, created_at, 'post' AS type FROM post WHERE ap_id ILIKE :url
        UNION ALL
        SELECT id, created_at, 'post_comment' AS type FROM post_comment WHERE ap_id ILIKE :url
        ORDER BY created_at DESC
        ";

        $pagerfanta = new Pagerfanta(new NativeQueryAdapter($conn, $sql, [
            'url' => "%$url%",
        ], transformer: $this->transformer));

        return $pagerfanta->getCurrentPageResults();
    }
}
