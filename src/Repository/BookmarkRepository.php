<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Bookmark;
use App\Entity\BookmarkList;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Pagination\NativeQueryAdapter;
use App\Pagination\Pagerfanta;
use App\Pagination\Transformation\ContentPopulationTransformer;
use App\Utils\SqlHelpers;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\PagerfantaInterface;
use Psr\Log\LoggerInterface;

/**
 * @method Bookmark|null find($id, $lockMode = null, $lockVersion = null)
 * @method Bookmark|null findOneBy(array $criteria, array $orderBy = null)
 * @method Bookmark[]    findAll()
 * @method Bookmark[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BookmarkRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly ContentPopulationTransformer $transformer,
    ) {
        parent::__construct($registry, Bookmark::class);
    }

    public function findByList(User $user, BookmarkList $list)
    {
        return $this->createQueryBuilder('b')
            ->where('b.user = :user')
            ->andWhere('b.list = :list')
            ->setParameter('user', $user)
            ->setParameter('list', $list)
            ->getQuery()
            ->getResult();
    }

    public function removeAllBookmarksForContent(User $user, Entry|EntryComment|Post|PostComment $content): void
    {
        if ($content instanceof Entry) {
            $contentWhere = 'entry_id = :id';
        } elseif ($content instanceof EntryComment) {
            $contentWhere = 'entry_comment_id = :id';
        } elseif ($content instanceof Post) {
            $contentWhere = 'post_id = :id';
        } elseif ($content instanceof PostComment) {
            $contentWhere = 'post_comment_id = :id';
        } else {
            throw new \LogicException();
        }

        $sql = "DELETE FROM bookmark WHERE user_id = :u AND $contentWhere";
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->executeStatement(['u' => $user->getId(), 'id' => $content->getId()]);
    }

    public function removeBookmarkFromList(User $user, BookmarkList $list, Entry|EntryComment|Post|PostComment $content): void
    {
        if ($content instanceof Entry) {
            $contentWhere = 'entry_id = :id';
        } elseif ($content instanceof EntryComment) {
            $contentWhere = 'entry_comment_id = :id';
        } elseif ($content instanceof Post) {
            $contentWhere = 'post_id = :id';
        } elseif ($content instanceof PostComment) {
            $contentWhere = 'post_comment_id = :id';
        } else {
            throw new \LogicException();
        }

        $sql = "DELETE FROM bookmark WHERE user_id = :u AND list_id = :l AND $contentWhere";
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->executeStatement(['u' => $user->getId(), 'l' => $list->getId(), 'id' => $content->getId()]);
    }

    public function findPopulatedByList(BookmarkList $list, Criteria $criteria, ?int $perPage = null): PagerfantaInterface
    {
        $entryWhereArr = ['b.list_id = :list'];
        $entryCommentWhereArr = ['b.list_id = :list'];
        $postWhereArr = ['b.list_id = :list'];
        $postCommentWhereArr = ['b.list_id = :list'];
        $parameters = [
            'list' => $list->getId(),
        ];

        $orderBy = match ($criteria->sortOption) {
            Criteria::SORT_OLD => 'ORDER BY i.created_at ASC',
            Criteria::SORT_TOP => 'ORDER BY i.score DESC, i.created_at DESC',
            Criteria::SORT_HOT => 'ORDER BY i.ranking DESC, i.created_at DESC',
            default => 'ORDER BY created_at DESC',
        };

        if (Criteria::AP_LOCAL === $criteria->federation) {
            $entryWhereArr[] = 'e.ap_id IS NULL';
            $entryCommentWhereArr[] = 'ec.ap_id IS NULL';
            $postWhereArr[] = 'p.ap_id IS NULL';
            $postCommentWhereArr[] = 'pc.ap_id IS NULL';
        }

        if ('all' !== $criteria->type) {
            $entryWhereArr[] = 'e.type = :type';
            $entryCommentWhereArr[] = 'false';
            $postWhereArr[] = 'false';
            $postCommentWhereArr[] = 'false';

            $parameters['type'] = $criteria->type;
        }

        if (Criteria::TIME_ALL !== $criteria->time) {
            $entryWhereArr[] = 'b.created_at > :time';
            $entryCommentWhereArr[] = 'b.created_at > :time';
            $postWhereArr[] = 'b.created_at > :time';
            $postCommentWhereArr[] = 'b.created_at > :time';

            $parameters['time'] = $criteria->getSince();
        }

        $entryWhere = SqlHelpers::makeWhereString($entryWhereArr);
        $entryCommentWhere = SqlHelpers::makeWhereString($entryCommentWhereArr);
        $postWhere = SqlHelpers::makeWhereString($postWhereArr);
        $postCommentWhere = SqlHelpers::makeWhereString($postCommentWhereArr);

        $sql = "
            SELECT * FROM (
                SELECT e.id AS id, e.ap_id AS ap_id, e.score AS score, e.ranking AS ranking, b.created_at AS created_at, 'entry' AS type FROM bookmark b
                    INNER JOIN entry e ON b.entry_id = e.id $entryWhere
                UNION
                SELECT ec.id AS id, ec.ap_id AS ap_id, (ec.up_votes + ec.favourite_count - ec.down_votes) AS score, ec.up_votes AS ranking, b.created_at AS created_at, 'entry_comment' AS type FROM bookmark b
                    INNER JOIN entry_comment ec ON b.entry_comment_id = ec.id $entryCommentWhere
                UNION
                SELECT p.id AS id, p.ap_id AS ap_id, p.score AS score, p.ranking AS ranking, b.created_at AS created_at, 'post' AS type FROM bookmark b
                    INNER JOIN post p ON b.post_id = p.id $postWhere
                UNION
                SELECT pc.id AS id, pc.ap_id AS ap_id, (pc.up_votes + pc.favourite_count - pc.down_votes) AS score, pc.up_votes AS ranking, b.created_at AS created_at, 'post_comment' AS type FROM bookmark b
                    INNER JOIN post_comment pc ON b.post_comment_id = pc.id $postCommentWhere
            ) i $orderBy
        ";

        $this->logger->info('bookmark list sql: {sql}', ['sql' => $sql]);

        $conn = $this->entityManager->getConnection();
        $adapter = new NativeQueryAdapter($conn, $sql, $parameters, transformer: $this->transformer);

        return Pagerfanta::createForCurrentPageWithMaxPerPage($adapter, $criteria->page, $perPage ?? EntryRepository::PER_PAGE);
    }
}
