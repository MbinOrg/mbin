<?php

declare(strict_types=1);

namespace App\Pagination\Transformation;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Repository\EntryCommentRepository;
use App\Repository\EntryRepository;
use App\Repository\MagazineRepository;
use App\Repository\PostCommentRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;

class ContentPopulationTransformer implements ResultTransformer
{
    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly EntryCommentRepository $entryCommentRepository,
        private readonly PostRepository $postRepository,
        private readonly PostCommentRepository $postCommentRepository,
        private readonly MagazineRepository $magazineRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function transform(iterable $input): iterable
    {
        $positionsArray = $this->buildPositionArray($input);
        $entryIds = $this->getOverviewIds((array) $input, 'entry');
        if (\count($entryIds) > 0) {
            $entries = $this->entryRepository->findBy(['id' => $entryIds]);
            $this->entryRepository->hydrate(...$entries);
        }

        $entryCommentIds = $this->getOverviewIds((array) $input, 'entry_comment');
        if (\count($entryCommentIds) > 0) {
            $entryComments = $this->entryCommentRepository->findBy(['id' => $entryCommentIds]);
            $this->entryCommentRepository->hydrate(...$entryComments);
        }

        $postIds = $this->getOverviewIds((array) $input, 'post');
        if (\count($postIds) > 0) {
            $post = $this->postRepository->findBy(['id' => $postIds]);
            $this->postRepository->hydrate(...$post);
        }

        $postCommentIds = $this->getOverviewIds((array) $input, 'post_comment');
        if (\count($postCommentIds) > 0) {
            $postComment = $this->postCommentRepository->findBy(['id' => $postCommentIds]);
            $this->postCommentRepository->hydrate(...$postComment);
        }

        $magazineIds = $this->getOverviewIds((array) $input, 'magazine');
        if (\count($magazineIds) > 0) {
            $magazines = $this->magazineRepository->findBy(['id' => $magazineIds]);
        }

        $userIds = $this->getOverviewIds((array) $input, 'user');
        if (\count($userIds) > 0) {
            $users = $this->userRepository->findBy(['id' => $userIds]);
        }

        return $this->applyPositions($positionsArray, $entries ?? [], $entryComments ?? [], $post ?? [], $postComment ?? [], $magazines ?? [], $users ?? []);
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
