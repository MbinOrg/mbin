<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\BookmarkList;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\User;
use App\Repository\BookmarkListRepository;
use App\Service\BookmarkManager;
use Twig\Extension\RuntimeExtensionInterface;

class BookmarkExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly BookmarkListRepository $bookmarkListRepository,
        private readonly BookmarkManager $bookmarkManager,
    ) {
    }

    /**
     * @return BookmarkList[]
     */
    public function getUsersBookmarkLists(User $user): array
    {
        return $this->bookmarkListRepository->findByUser($user);
    }

    public function getBookmarkListEntryCount(BookmarkList $list): int
    {
        return $list->entities->count();
    }

    public function isContentBookmarked(User $user, Entry|EntryComment|Post|PostComment $content): bool
    {
        return $this->bookmarkManager->isBookmarked($user, $content);
    }

    public function isContentBookmarkedInList(User $user, BookmarkList $list, Entry|EntryComment|Post|PostComment $content): bool
    {
        return $this->bookmarkManager->isBookmarkedInList($user, $list, $content);
    }
}
