<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Entry;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class EntryVoter extends Voter
{
    public const CREATE = 'create';
    public const EDIT = 'edit';
    public const DELETE = 'delete';
    public const PURGE = 'purge';
    public const COMMENT = 'comment';
    public const VOTE = 'vote';
    public const MODERATE = 'moderate';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof Entry
            && \in_array(
                $attribute,
                [self::CREATE, self::EDIT, self::DELETE, self::PURGE, self::COMMENT, self::VOTE, self::MODERATE],
                true
            );
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::EDIT => $this->canEdit($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            self::PURGE => $this->canPurge($subject, $user),
            self::COMMENT => $this->canComment($subject, $user),
            self::VOTE => $this->canVote($subject, $user),
            self::MODERATE => $this->canModerate($subject, $user),
            default => throw new \LogicException(),
        };
    }

    private function canEdit(Entry $entry, User $user): bool
    {
        if ($user->isAdmin() || $user->isModerator()) {
            return true;
        }

        if ($entry->user === $user) {
            return true;
        }

        return false;
    }

    private function canDelete(Entry $entry, User $user): bool
    {
        if ($user->isAdmin() || $user->isModerator()) {
            return true;
        }

        if ($entry->user === $user) {
            return true;
        }

        if ($entry->magazine->userIsModerator($user)) {
            return true;
        }

        return false;
    }

    private function canPurge(Entry $entry, User $user): bool
    {
        return $user->isAdmin();
    }

    private function canComment(Entry $entry, User $user): bool
    {
        return !$entry->magazine->isBanned($user);
    }

    private function canVote(Entry $entry, User $user): bool
    {
        //        if ($entry->user === $user) {
        //            return false;
        //        }

        if ($entry->magazine->isBanned($user)) {
            return false;
        }

        return true;
    }

    private function canModerate(Entry $entry, User $user): bool
    {
        return $entry->magazine->userIsModerator($user) || $user->isAdmin() || $user->isModerator();
    }
}
