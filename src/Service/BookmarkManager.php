<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Bookmark;
use App\Entity\BookmarkList;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Repository\BookmarkListRepository;
use App\Repository\BookmarkRepository;
use Doctrine\ORM\EntityManagerInterface;

class BookmarkManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookmarkListRepository $bookmarkListRepository,
        private readonly BookmarkRepository $bookmarkRepository,
    ) {
    }

    public function createList(User $user, string $name): BookmarkList
    {
        $list = new BookmarkList($user, $name);
        $this->entityManager->persist($list);
        $this->entityManager->flush();

        return $list;
    }

    public function isBookmarked(User $user, Entry|EntryComment|Post|PostComment $content): bool
    {
        if ($content instanceof Entry) {
            return !empty($this->bookmarkRepository->findBy(['user' => $user, 'entry' => $content]));
        } elseif ($content instanceof EntryComment) {
            return !empty($this->bookmarkRepository->findBy(['user' => $user, 'entryComment' => $content]));
        } elseif ($content instanceof Post) {
            return !empty($this->bookmarkRepository->findBy(['user' => $user, 'post' => $content]));
        } elseif ($content instanceof PostComment) {
            return !empty($this->bookmarkRepository->findBy(['user' => $user, 'postComment' => $content]));
        }

        return false;
    }

    public function isBookmarkedInList(User $user, BookmarkList $list, Entry|EntryComment|Post|PostComment $content): bool
    {
        if ($content instanceof Entry) {
            return null !== $this->bookmarkRepository->findOneBy(['user' => $user, 'list' => $list, 'entry' => $content]);
        } elseif ($content instanceof EntryComment) {
            return null !== $this->bookmarkRepository->findOneBy(['user' => $user, 'list' => $list, 'entryComment' => $content]);
        } elseif ($content instanceof Post) {
            return null !== $this->bookmarkRepository->findOneBy(['user' => $user, 'list' => $list, 'post' => $content]);
        } elseif ($content instanceof PostComment) {
            return null !== $this->bookmarkRepository->findOneBy(['user' => $user, 'list' => $list, 'postComment' => $content]);
        }

        return false;
    }

    public function addBookmarkToDefaultList(User $user, Entry|EntryComment|Post|PostComment $content): void
    {
        $list = $this->bookmarkListRepository->findOneByUserDefault($user);
        $this->addBookmark($user, $list, $content);
    }

    public function addBookmark(User $user, BookmarkList $list, Entry|EntryComment|Post|PostComment $content): void
    {
        $bookmark = new Bookmark($user, $list);
        $bookmark->setContent($content);
        $this->entityManager->persist($bookmark);
        $this->entityManager->flush();
    }

    public static function GetClassFromSubjectType(string $subjectType): string
    {
        return match ($subjectType) {
            'entry' => Entry::class,
            'entry_comment' => EntryComment::class,
            'post' => Post::class,
            'post_comment' => PostComment::class,
            default => throw new \LogicException("cannot match type $subjectType")
        };
    }
}
