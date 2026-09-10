<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Domain;
use App\Entity\Entry;
use App\Entity\Post;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\RuntimeExtensionInterface;

readonly class SubjectExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function userCanSeeEntry(Entry $entry): bool
    {
        /* @var ?User $user */
        $user = $this->security->getUser();
        return $this->userCanSeeEntryPost($entry, $user, $entry->domain);
    }

    public function userCanSeePost(Post $post): bool
    {
        /* @var ?User $user */
        $user = $this->security->getUser();
        return $this->userCanSeeEntryPost($post, $user);
    }

    private function userCanSeeEntryPost(Entry|Post $content, ?User $user, ?Domain $domain = null): bool
    {
        $author = $content->user;

        if (null !== $user) {
            if ($user->isBlocked($author)
                || $user->isBlockedMagazine($content->getMagazine())
                || (null !== $domain && $user->isBlockedDomain($domain))) {
                return false;
            }

            if ($user->hideAdult && $content->isAdult()) {
                return false;
            }
        }

        if ($content->isVisible() && $author->isVisible()) {
            return true;
        }
        if (($content->isPrivate() || $author->isPrivate()) && $user->isFollowing($author)) {
            return true;
        }

        if (null !== $user) {
            if ($user->isAdmin() || $user->isModerator()) {
                return true;
            }
            if ($content->getMagazine()->userIsModerator($user)) {
                return true;
            }
        }

        return false;
    }
}
