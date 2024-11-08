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

        $result = array_merge($entries, $entryComments, $post, $postComment);
        uasort($result, fn ($a, $b) => $a->getCreatedAt() > $b->getCreatedAt() ? -1 : 1);

        return $result;
    }

    private function getOverviewIds(array $result, string $type): array
    {
        $result = array_filter($result, fn ($subject) => $subject['type'] === $type);

        return array_map(fn ($subject) => $subject['id'], $result);
    }
}
