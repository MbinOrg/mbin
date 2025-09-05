<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\MagazineLog;
use App\Entity\Post;
use App\Entity\PostComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @method MagazineLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method MagazineLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method MagazineLog[]    findAll()
 * @method MagazineLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MagazineLogRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 25;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MagazineLog::class);
    }

    /**
     * @param string[]|null $types modlog types
     */
    public function findByCustom(int $page, int $perPage = self::PER_PAGE, ?array $types = null, ?Magazine $magazine = null): PagerfantaInterface
    {
        $qb = $this->createQueryBuilder('ml');

        if (null !== $types && \sizeof($types) > 0) {
            $wheres = array_map(fn ($type) => 'ml INSTANCE OF '.MagazineLog::DISCRIMINATOR_MAP[$type], $types);
            $qb = $qb->where(implode(' OR ', $wheres));
            if (null !== $magazine) {
                $qb = $qb->andWhere('ml.magazine = :magazine')
                    ->setParameter('magazine', $magazine);
            }
        } elseif (null !== $magazine) {
            $qb = $qb->where('ml.magazine = :magazine')
                ->setParameter('magazine', $magazine);
        }

        $qb->orderBy('ml.createdAt', 'DESC');

        $pager = new Pagerfanta(new QueryAdapter($qb));
        try {
            $pager->setMaxPerPage($perPage);
            $pager->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pager;
    }

    public function removeEntryLogs(Entry $entry): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'DELETE FROM magazine_log AS m WHERE m.entry_id = :entryId';

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('entryId', $entry->getId());

        $stmt->executeQuery();
    }

    public function removeEntryCommentLogs(EntryComment $comment): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'DELETE FROM magazine_log AS m WHERE m.entry_comment_id = :commentId';

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('commentId', $comment->getId());

        $stmt->executeQuery();
    }

    public function removePostLogs(Post $post): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'DELETE FROM magazine_log AS m WHERE m.post_id = :postId';

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('postId', $post->getId());

        $stmt->executeQuery();
    }

    public function removePostCommentLogs(PostComment $comment): void
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'DELETE FROM magazine_log AS m WHERE m.post_comment_id = :commentId';

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('commentId', $comment->getId());

        $stmt->executeQuery();
    }
}
