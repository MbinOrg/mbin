<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\BookmarkExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BookmarkExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_bookmarked', [BookmarkExtensionRuntime::class, 'isContentBookmarked']),
            new TwigFunction('is_bookmarked_in_list', [BookmarkExtensionRuntime::class, 'isContentBookmarkedInList']),
            new TwigFunction('get_bookmark_lists', [BookmarkExtensionRuntime::class, 'getUsersBookmarkLists']),
            new TwigFunction('get_bookmark_list_entry_count', [BookmarkExtensionRuntime::class, 'getBookmarkListEntryCount']),
        ];
    }
}
