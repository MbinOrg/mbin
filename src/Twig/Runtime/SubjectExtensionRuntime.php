<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

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

        if (null !== $user) {
            if ($user->isBlocked($entry->user)
                || $user->isBlockedMagazine($entry->getMagazine())
                || (null !== $entry->domain && $user->isBlockedDomain($entry->domain))) {
                return false;
            }
        }

        if ($entry->isVisible() && $entry->user->isVisible()) {
            return true;
        }

        if (null !== $user) {
            if ($user->isAdmin() || $user->isModerator()) {
                return true;
            }
            if ($entry->getMagazine()->userIsModerator($user)) {
                return true;
            }
        }

        return false;
    }

    public function userCanSeePost(Post $post): bool
    {
        /* @var ?User $user */
        $user = $this->security->getUser();

        if (null !== $user) {
            if ($user->isBlocked($post->user) || $user->isBlockedMagazine($post->getMagazine())) {
                return false;
            }
        }

        if ($post->isVisible() && $post->user->isVisible()) {
            return true;
        }

        if (null !== $user) {
            if ($user->isAdmin() || $user->isModerator()) {
                return true;
            }
            if ($post->getMagazine()->userIsModerator($user)) {
                return true;
            }
        }

        return false;
    }
}
