<?php

declare(strict_types=1);

namespace App\Pagination\Transformation;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use Doctrine\ORM\EntityManagerInterface;

class ContentPopulationTransformer implements ResultTransformer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function transform(iterable $input): iterable
    {
        $positionsArray = $this->buildPositionArray($input);
        $entries = $this->entityManager->getRepository(Entry::class)->findBy(
            ['id' => $this->getOverviewIds((array) $input, 'entry')]
        );
        $entryComments = $this->entityManager->getRepository(EntryComment::class)->findBy(
            ['id' => $this->getOverviewIds((array) $input, 'entry_comment')]
        );
        $post = $this->entityManager->getRepository(Post::class)->findBy(
            ['id' => $this->getOverviewIds((array) $input, 'post')]
        );
        $postComment = $this->entityManager->getRepository(PostComment::class)->findBy(
            ['id' => $this->getOverviewIds((array) $input, 'post_comment')]
        );

        return $this->applyPositions($positionsArray, $entries, $entryComments, $post, $postComment);
    }

    private function getOverviewIds(array $result, string $type): array
    {
        $result = array_filter($result, fn ($subject) => $subject['type'] === $type);

        return array_map(fn ($subject) => $subject['id'], $result);
    }

    /**
     * @return int[][]
     */
    private function buildPositionArray(iterable $input): array
    {
        $entryPositions = [];
        $entryCommentPositions = [];
        $postPositions = [];
        $postCommentPositions = [];
        $i = 0;
        foreach ($input as $current) {
            switch ($current['type']) {
                case 'entry':
                    $entryPositions[$current['id']] = $i;
                    break;
                case 'entry_comment':
                    $entryCommentPositions[$current['id']] = $i;
                    break;
                case 'post':
                    $postPositions[$current['id']] = $i;
                    break;
                case 'post_comment':
                    $postCommentPositions[$current['id']] = $i;
                    break;
            }
            ++$i;
        }

        return [
            'entry' => $entryPositions,
            'entry_comment' => $entryCommentPositions,
            'post' => $postPositions,
            'post_comment' => $postCommentPositions,
        ];
    }

    /**
     * @param int[][]        $positionsArray
     * @param Entry[]        $entries
     * @param EntryComment[] $entryComments
     * @param Post[]         $posts
     * @param PostComment[]  $postComments
     */
    private function applyPositions(array $positionsArray, array $entries, array $entryComments, array $posts, array $postComments): array
    {
        $result = [];
        foreach ($entries as $entry) {
            $result[$positionsArray['entry'][$entry->getId()]] = $entry;
        }
        foreach ($entryComments as $entryComment) {
            $result[$positionsArray['entry_comment'][$entryComment->getId()]] = $entryComment;
        }
        foreach ($posts as $post) {
            $result[$positionsArray['post'][$post->getId()]] = $post;
        }
        foreach ($postComments as $postComment) {
            $result[$positionsArray['post_comment'][$postComment->getId()]] = $postComment;
        }
        ksort($result, SORT_NUMERIC);

        return $result;
    }
}
