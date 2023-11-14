<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\PostComment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PostCommentVoter extends Voter
{
    public const EDIT = 'edit';
    public const DELETE = 'delete';
    public const PURGE = 'purge';
    public const VOTE = 'vote';
    public const MODERATE = 'moderate';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof PostComment && \in_array(
            $attribute,
            [self::EDIT, self::DELETE, self::PURGE, self::VOTE, self::MODERATE],
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
            self::PURGE => $this->canPurge($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            self::VOTE => $this->canVote($subject, $user),
            self::MODERATE => $this->canModerate($subject, $user),
            default => throw new \LogicException(),
        };
    }

    private function canEdit(PostComment $comment, User $user): bool
    {
        if ($comment->user === $user) {
            return true;
        }

        return false;
    }

    private function canPurge(PostComment $comment, User $user): bool
    {
        return $user->isAdmin();
    }

    private function canDelete(PostComment $comment, User $user): bool
    {
        if ($user->isAdmin() || $user->isModerator()) {
            return true;
        }

        if ($comment->user === $user) {
            return true;
        }

        if ($comment->post->magazine->userIsModerator($user)) {
            return true;
        }

        return false;
    }

    private function canVote(PostComment $comment, User $user): bool
    {
        //        if ($comment->user === $user) {
        //            return false;
        //        }

        if ($comment->post->magazine->isBanned($user)) {
            return false;
        }

        return true;
    }

    private function canModerate(PostComment $comment, User $user): bool
    {
        return $comment->magazine->userIsModerator($user) || $user->isAdmin() || $user->isModerator();
    }
}
