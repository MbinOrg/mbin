<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Hashtag;
use App\Entity\HashtagLink;
use App\Entity\Post;
use App\Entity\PostComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method HashtagLink|null find($id, $lockMode = null, $lockVersion = null)
 * @method HashtagLink|null findOneBy(array $criteria, array $orderBy = null)
 * @method HashtagLink[]    findAll()
 * @method HashtagLink[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagLinkRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($registry, HashtagLink::class);
    }

    /**
     * @return string[]
     */
    public function getTagsOfContent(Entry|EntryComment|Post|PostComment $content): array
    {
        if ($content instanceof Entry) {
            return $this->getTagsOfEntry($content);
        } elseif ($content instanceof EntryComment) {
            return $this->getTagsOfEntryComment($content);
        } elseif ($content instanceof Post) {
            return $this->getTagsOfPost($content);
        } elseif ($content instanceof PostComment) {
            return $this->getTagsOfPostComment($content);
        } else {
            // this is unreachable because of the strict types
            throw new \LogicException('Cannot handle content of type '.\get_class($content));
        }
    }

    /**
     * @return string[]
     */
    private function getTagsOfEntry(Entry $entry): array
    {
        $result = $this->findBy(['entry' => $entry]);

        return array_map(fn ($row) => $row->hashtag->tag, $result);
    }

    public function removeTagOfEntry(Entry $entry, Hashtag $tag): void
    {
        $link = $this->findOneBy(['entry' => $entry, 'hashtag' => $tag]);
        $this->entityManager->remove($link);
        $this->entityManager->flush();
    }

    public function addTagToEntry(Entry $entry, Hashtag $tag): void
    {
        $link = new HashtagLink();
        $link->entry = $entry;
        $link->hashtag = $tag;
        $this->entityManager->persist($link);
        $this->entityManager->flush();
    }

    public function entryHasTag(Entry $entry, Hashtag $tag): bool
    {
        return null !== $this->findOneBy(['entry' => $entry, 'hashtag' => $tag]);
    }

    /**
     * @return string[]
     */
    private function getTagsOfEntryComment(EntryComment $entryComment): array
    {
        $result = $this->findBy(['entryComment' => $entryComment]);

        return array_map(fn ($row) => $row->hashtag->tag, $result);
    }

    public function removeTagOfEntryComment(EntryComment $entryComment, Hashtag $tag): void
    {
        $link = $this->findOneBy(['entryComment' => $entryComment, 'hashtag' => $tag]);
        $this->entityManager->remove($link);
        $this->entityManager->flush();
    }

    public function addTagToEntryComment(EntryComment $entryComment, Hashtag $tag): void
    {
        $link = new HashtagLink();
        $link->entryComment = $entryComment;
        $link->hashtag = $tag;
        $this->entityManager->persist($link);
        $this->entityManager->flush();
    }

    /**
     * @return string[]
     */
    private function getTagsOfPost(Post $post): array
    {
        $result = $this->findBy(['post' => $post]);

        return array_map(fn ($row) => $row->hashtag->tag, $result);
    }

    public function removeTagOfPost(Post $post, Hashtag $tag): void
    {
        $link = $this->findOneBy(['post' => $post, 'hashtag' => $tag]);
        $this->entityManager->remove($link);
        $this->entityManager->flush();
    }

    public function addTagToPost(Post $post, Hashtag $tag): void
    {
        $link = new HashtagLink();
        $link->post = $post;
        $link->hashtag = $tag;
        $this->entityManager->persist($link);
        $this->entityManager->flush();
    }

    /**
     * @return string[]
     */
    private function getTagsOfPostComment(PostComment $postComment): array
    {
        $result = $this->findBy(['postComment' => $postComment]);

        return array_map(fn ($row) => $row->hashtag->tag, $result);
    }

    public function removeTagOfPostComment(PostComment $postComment, Hashtag $tag): void
    {
        $link = $this->findOneBy(['postComment' => $postComment, 'hashtag' => $tag]);
        $this->entityManager->remove($link);
        $this->entityManager->flush();
    }

    public function addTagToPostComment(PostComment $postComment, Hashtag $tag): void
    {
        $link = new HashtagLink();
        $link->postComment = $postComment;
        $link->hashtag = $tag;
        $this->entityManager->persist($link);
        $this->entityManager->flush();
    }
}
