<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contracts\VisibilityInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Pagerfanta\Pagerfanta;
use Pagerfanta\PagerfantaInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TagRepository
{
    public const PER_PAGE = 25;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findOverall(int $page, string $tag): PagerfantaInterface
    {
        // @todo union adapter
        $conn = $this->entityManager->getConnection();
        $sql = "
        (SELECT id, created_at, 'entry' AS type FROM entry WHERE tags @> :tag = true AND visibility = :visibility)
        UNION ALL
        (SELECT id, created_at, 'entry_comment' AS type FROM entry_comment WHERE tags @> :tag = true AND visibility = :visibility)
        UNION ALL
        (SELECT id, created_at, 'post' AS type FROM post WHERE tags @> :tag = true AND visibility = :visibility)
        UNION ALL
        (SELECT id, created_at, 'post_comment' AS type FROM post_comment WHERE tags @> :tag = true AND visibility = :visibility)
        ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('tag', "\"$tag\"");
        $stmt->bindValue('visibility', VisibilityInterface::VISIBILITY_VISIBLE);
        $stmt = $stmt->executeQuery();

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

        $entries = $this->entityManager->getRepository(Entry::class)->findBy(
            ['id' => $this->getOverviewIds((array) $result, 'entry')]
        );
        $entryComments = $this->entityManager->getRepository(EntryComment::class)->findBy(
            ['id' => $this->getOverviewIds((array) $result, 'entry_comment')]
        );
        $post = $this->entityManager->getRepository(Post::class)->findBy(
            ['id' => $this->getOverviewIds((array) $result, 'post')]
        );
        $postComment = $this->entityManager->getRepository(PostComment::class)->findBy(
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
}
