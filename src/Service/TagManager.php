<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\EntryCommentDto;
use App\DTO\EntryDto;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Hashtag;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Repository\TagLinkRepository;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\ArrayShape;

class TagManager
{
    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly TagLinkRepository $tagLinkRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TagExtractor $tagExtractor,
    ) {
    }

    public function extract(?string $val, ?string $magazineName = null): ?array
    {
        return $this->tagExtractor->extract($val, $magazineName);
    }

    /**
     * @param string[] $newTags
     */
    public function updateEntryTags(Entry $entry, array $newTags): void
    {
        $this->updateTags($newTags,
            fn () => $this->tagLinkRepository->getTagsOfEntry($entry),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->removeTagOfEntry($entry, $hashtag),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->addTagToEntry($entry, $hashtag)
        );
    }

    public function getTagsFromEntryDto(EntryDto $dto): array
    {
        return array_unique(
            array_filter(
                array_merge(
                    $dto->tags ?? [],
                    $this->tagExtractor->extract($dto->body ?? '') ?? []
                )
            )
        );
    }

    /**
     * @param string[] $newTags
     */
    public function updateEntryCommentTags(EntryComment $entryComment, array $newTags): void
    {
        $this->updateTags($newTags,
            fn () => $this->tagLinkRepository->getTagsOfEntryComment($entryComment),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->removeTagOfEntryComment($entryComment, $hashtag),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->addTagToEntryComment($entryComment, $hashtag)
        );
    }

    public function getTagsFromEntryCommentDto(EntryCommentDto $dto): array
    {
        return array_unique(
            array_filter(
                array_merge(
                    $dto->tags ?? [],
                    $this->tagExtractor->extract($dto->body ?? '') ?? []
                )
            )
        );
    }

    /**
     * @param string[] $newTags
     */
    public function updatePostTags(Post $post, array $newTags): void
    {
        $this->updateTags($newTags,
            fn () => $this->tagLinkRepository->getTagsOfPost($post),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->removeTagOfPost($post, $hashtag),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->addTagToPost($post, $hashtag)
        );
    }

    /**
     * @param string[] $newTags
     */
    public function updatePostCommentTags(PostComment $postComment, array $newTags): void
    {
        $this->updateTags($newTags,
            fn () => $this->tagLinkRepository->getTagsOfPostComment($postComment),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->removeTagOfPostComment($postComment, $hashtag),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->addTagToPostComment($postComment, $hashtag)
        );
    }

    /**
     * @param string[]                $newTags
     * @param callable(): string[]    $getTags   a callable that should return all the tags of the entity as a string array
     * @param callable(Hashtag): void $removeTag a callable that gets a string as parameter and should remove the tag
     * @param callable(Hashtag): void $addTag
     */
    private function updateTags(array $newTags, callable $getTags, callable $removeTag, callable $addTag): void
    {
        $oldTags = $getTags();
        $actions = $this->intersectOldAndNewTags($oldTags, $newTags);
        foreach ($actions['tagsToRemove'] as $tag) {
            $removeTag($this->tagRepository->findOneBy(['tag' => $tag]));
        }
        foreach ($actions['tagsToCreate'] as $tag) {
            $tagEntity = $this->tagRepository->findOneBy(['tag' => $tag]);
            if (null === $tagEntity) {
                $tagEntity = $this->tagRepository->create($tag);
            }
            $addTag($tagEntity);
        }
    }

    #[ArrayShape([
        'tagsToRemove' => 'string[]',
        'tagsToCreate' => 'string[]',
    ])]
    private function intersectOldAndNewTags(array $oldTags, array $newTags): array
    {
        /** @var string[] $tagsToRemove */
        $tagsToRemove = [];
        /** @var string[] $tagsToCreate */
        $tagsToCreate = [];
        foreach ($oldTags as $tag) {
            if (!\in_array($tag, $newTags)) {
                $tagsToRemove[] = $tag;
            }
        }
        foreach ($newTags as $tag) {
            if (!\in_array($tag, $oldTags)) {
                $tagsToCreate[] = $tag;
            }
        }

        return [
            'tagsToCreate' => $tagsToCreate,
            'tagsToRemove' => $tagsToRemove,
        ];
    }

    public function ban(Hashtag $hashtag): void
    {
        $hashtag->banned = true;
        $this->entityManager->persist($hashtag);
        $this->entityManager->flush();
    }

    public function unban(Hashtag $hashtag): void
    {
        $hashtag->banned = false;
        $this->entityManager->persist($hashtag);
        $this->entityManager->flush();
    }

    public function isAnyTagBanned(?array $tags): bool
    {
        if ($tags) {
            $result = $this->tagRepository->findBy(['tag' => $tags, 'banned' => true]);
            if ($result && 0 !== \sizeof($result)) {
                return true;
            }
        }

        return false;
    }
}
