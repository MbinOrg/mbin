<?php

declare(strict_types=1);

namespace App\Pagination\Transformation;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Repository\EntryCommentRepository;
use App\Repository\EntryRepository;
use App\Repository\PostCommentRepository;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;

class ContentPopulationTransformer implements ResultTransformer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntryRepository $entryRepository,
        private readonly EntryCommentRepository $entryCommentRepository,
        private readonly PostRepository $postRepository,
        private readonly PostCommentRepository $postCommentRepository,
    ) {
    }

    public function transform(iterable $input): iterable
    {
        $positionsArray = $this->buildPositionArray($input);
        $entries = $this->entryRepository->findBy(
            ['id' => $this->getOverviewIds((array) $input, 'entry')]
        );
        $this->entryRepository->hydrate(...$entries);
        $entryComments = $this->entryCommentRepository->findBy(
            ['id' => $this->getOverviewIds((array) $input, 'entry_comment')]
        );
        $this->entryCommentRepository->hydrate(...$entryComments);
        $post = $this->postRepository->findBy(
            ['id' => $this->getOverviewIds((array) $input, 'post')]
        );
        $this->postRepository->hydrate(...$post);
        $postComment = $this->postCommentRepository->findBy(
            ['id' => $this->getOverviewIds((array) $input, 'post_comment')]
        );
        $this->postCommentRepository->hydrate(...$postComment);
        $magazines = $this->entityManager->getRepository(Magazine::class)->findBy(
            ['id' => $this->getOverviewIds((array) $input, 'magazine')]
        );
        $users = $this->entityManager->getRepository(User::class)->findBy(
            ['id' => $this->getOverviewIds((array) $input, 'user')]
        );

        return $this->applyPositions($positionsArray, $entries, $entryComments, $post, $postComment, $magazines, $users);
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
        $userPositions = [];
        $magazinePositions = [];
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
                case 'magazine':
                    $magazinePositions[$current['id']] = $i;
                    break;
                case 'user':
                    $userPositions[$current['id']] = $i;
                    break;
            }
            ++$i;
        }

        return [
            'entry' => $entryPositions,
            'entry_comment' => $entryCommentPositions,
            'post' => $postPositions,
            'post_comment' => $postCommentPositions,
            'magazine' => $magazinePositions,
            'user' => $userPositions,
        ];
    }

    /**
     * @param int[][]        $positionsArray
     * @param Entry[]        $entries
     * @param EntryComment[] $entryComments
     * @param Post[]         $posts
     * @param PostComment[]  $postComments
     */
    private function applyPositions(array $positionsArray, array $entries, array $entryComments, array $posts, array $postComments, array $magazines, array $users): array
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
        foreach ($magazines as $magazine) {
            $result[$positionsArray['magazine'][$magazine->getId()]] = $magazine;
        }
        foreach ($users as $user) {
            $result[$positionsArray['user'][$user->getId()]] = $user;
        }
        ksort($result, SORT_NUMERIC);

        return $result;
    }
}
