<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Hashtag;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Repository\TagLinkRepository;
use App\Repository\TagRepository;
use App\Utils\RegPatterns;
use Doctrine\ORM\EntityManagerInterface;
use JetBrains\PhpStorm\ArrayShape;

class TagManager
{
    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly TagLinkRepository $tagLinkRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function joinTagsToBody(string $body, array $tags): string
    {
        $current = $this->extract($body) ?? [];

        $join = array_unique(array_merge(array_diff($tags, $current)));

        if (!empty($join)) {
            if (!empty($body)) {
                $lastTag = end($current);
                if (($lastTag && !str_ends_with($body, $lastTag)) || !$lastTag) {
                    $body = $body.PHP_EOL.PHP_EOL;
                }
            }

            $body = $body.' #'.implode(' #', $join);
        }

        return $body;
    }

    public function extract(string $val, string $magazineName = null): ?array
    {
        preg_match_all(RegPatterns::LOCAL_TAG, $val, $matches);

        $result = $matches[1];
        $result = array_map(fn ($tag) => mb_strtolower(trim($tag)), $result);

        $result = array_values($result);

        $result = array_map(fn ($tag) => $this->transliterate($tag), $result);

        if ($magazineName) {
            $result = array_diff($result, [$magazineName]);
        }

        return \count($result) ? array_unique(array_values($result)) : null;
    }

    /**
     * transliterate and normalize a hashtag identifier.
     *
     * mostly recreates Mastodon's hashtag normalization rules, using ICU rules
     * - try to transliterate modified latin characters to ASCII regions
     * - normalize widths for fullwidth/halfwidth letters
     * - strip characters that shouldn't be part of a hashtag
     *   (borrowed the character set from Mastodon)
     *
     * @param string $tag input hashtag identifier to normalize
     *
     * @return string normalized hashtag identifier
     *
     * @see https://github.com/mastodon/mastodon/blob/main/app/lib/hashtag_normalizer.rb
     * @see https://github.com/mastodon/mastodon/blob/main/app/models/tag.rb
     */
    public function transliterate(string $tag): string
    {
        $rules = <<<'ENDRULE'
        :: Latin-ASCII;
        :: [\uFF00-\uFFEF] NFKC;
        :: [^[:alnum:][\u0E47-\u0E4E][_\u00B7\u30FB\u200c]] Remove;
        ENDRULE;

        $normalizer = \Transliterator::createFromRules($rules);

        return iconv('UTF-8', 'UTF-8//TRANSLIT', $normalizer->transliterate($tag));
    }

    public function updateEntryTags(Entry $entry, ?string $body): void
    {
        $this->updateTags($body, $entry->magazine?->name,
            fn () => $this->tagLinkRepository->getTagsOfEntry($entry),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->removeTagOfEntry($entry, $hashtag),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->addTagToEntry($entry, $hashtag)
        );
    }

    public function updateEntryCommentTags(EntryComment $entryComment, ?string $body): void
    {
        $this->updateTags($body, $entryComment->magazine?->name,
            fn () => $this->tagLinkRepository->getTagsOfEntryComment($entryComment),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->removeTagOfEntryComment($entryComment, $hashtag),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->addTagToEntryComment($entryComment, $hashtag)
        );
    }

    public function updatePostTags(Post $post, ?string $body): void
    {
        $this->updateTags($body, $post->magazine?->name,
            fn () => $this->tagLinkRepository->getTagsOfPost($post),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->removeTagOfPost($post, $hashtag),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->addTagToPost($post, $hashtag)
        );
    }

    public function updatePostCommentTags(PostComment $postComment, ?string $body): void
    {
        $this->updateTags($body, $postComment->magazine?->name,
            fn () => $this->tagLinkRepository->getTagsOfPostComment($postComment),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->removeTagOfPostComment($postComment, $hashtag),
            fn (Hashtag $hashtag) => $this->tagLinkRepository->addTagToPostComment($postComment, $hashtag)
        );
    }

    /**
     * @param callable(): string[]    $getTags   a callable that should return all the tags of the entity as a string array
     * @param callable(Hashtag): void $removeTag a callable that gets a string as parameter and should remove the tag
     * @param callable(Hashtag): void $addTag
     */
    private function updateTags(?string $body, ?string $magazineName, callable $getTags, callable $removeTag, callable $addTag): void
    {
        $newTags = null !== $body ? $this->extract($body, $magazineName) : [];
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
