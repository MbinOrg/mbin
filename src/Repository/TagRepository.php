<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Hashtag;
use App\Pagination\NativeQueryAdapter;
use App\Pagination\Transformation\ContentPopulationTransformer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use JetBrains\PhpStorm\ArrayShape;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @method Hashtag|null find($id, $lockMode = null, $lockVersion = null)
 * @method Hashtag|null findOneBy(array $criteria, array $orderBy = null)
 * @method Hashtag[]    findAll()
 * @method Hashtag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 25;

    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
        private readonly TagLinkRepository $tagLinkRepository,
        private readonly ContentPopulationTransformer $populationTransformer
    ) {
        parent::__construct($registry, Hashtag::class);
    }

    public function findOverall(int $page, string $tag): PagerfantaInterface
    {
        $hashtag = $this->findBy(['tag' => $tag]);
        $countAll = $this->tagLinkRepository->createQueryBuilder('link')
            ->select('count(link.id)')
            ->where('link.hashtag = :tag')
            ->setParameter(':tag', $hashtag)
            ->getQuery()
            ->getSingleScalarResult();

        $conn = $this->entityManager->getConnection();
        $sql = "SELECT e.id, e.created_at, 'entry' AS type FROM entry e 
                INNER JOIN hashtag_link l ON e.id = l.entry_id 
                INNER JOIN hashtag h ON l.hashtag_id = h.id AND h.tag = :tag
            WHERE visibility = :visibility
        UNION ALL
        SELECT ec.id, ec.created_at, 'entry_comment' AS type FROM entry_comment ec
                INNER JOIN hashtag_link l ON ec.id = l.entry_comment_id 
                INNER JOIN hashtag h ON l.hashtag_id = h.id AND h.tag = :tag
            WHERE visibility = :visibility
        UNION ALL
        SELECT p.id, p.created_at, 'post' AS type FROM post p
                INNER JOIN hashtag_link l ON p.id = l.post_id 
                INNER JOIN hashtag h ON l.hashtag_id = h.id AND h.tag = :tag
            WHERE visibility = :visibility
        UNION ALL
        SELECT pc.id, created_at, 'post_comment' AS type FROM post_comment pc
                INNER JOIN hashtag_link l ON pc.id = l.post_comment_id 
                INNER JOIN hashtag h ON l.hashtag_id = h.id AND h.tag = :tag WHERE visibility = :visibility
        ORDER BY created_at DESC";

        $adapter = new NativeQueryAdapter($conn, $sql, [
            'tag' => $tag,
            'visibility' => VisibilityInterface::VISIBILITY_VISIBLE,
        ], $countAll, $this->populationTransformer);

        $pagerfanta = new Pagerfanta($adapter);

        try {
            $pagerfanta->setMaxPerPage(self::PER_PAGE);
            $pagerfanta->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        return $pagerfanta;
    }

    public function create(string $tag): Hashtag
    {
        $entity = new Hashtag();
        $entity->tag = $tag;
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    #[ArrayShape([
        'entry' => 'int',
        'entry_comment' => 'int',
        'post' => 'int',
        'post_comment' => 'int',
    ])]
    public function getCounts(string $tag): ?array
    {
        $conn = $this->entityManager->getConnection();
        $stmt = $conn->prepare('SELECT COUNT(entry_id) as entry, COUNT(entry_comment_id) as entry_comment, COUNT(post_id) as post, COUNT(post_comment_id) as post_comment 
            FROM hashtag_link INNER JOIN public.hashtag h ON h.id = hashtag_link.hashtag_id AND h.tag = :tag GROUP BY h.tag');
        $stmt->bindValue('tag', $tag);
        $result = $stmt->executeQuery()->fetchAllAssociative();
        if (1 === \sizeof($result)) {
            return $result[0];
        }

        return null;
    }
}
