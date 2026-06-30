<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\MessageThread;
use App\Entity\User;
use App\Repository\MessageThreadRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class MessageThreadVoter extends Voter
{
    public const SHOW = 'show';
    public const REPLY = 'reply';

    public function  __construct(
        private readonly MessageThreadRepository $messageThreadRepository,
    )
    {

    }

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof MessageThread
            && \in_array(
                $attribute,
                [self::SHOW, self::REPLY],
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
            self::SHOW => $this->canShow($subject, $user),
            self::REPLY => $this->canReply($subject, $user),
            default => throw new \LogicException(),
        };
    }

    private function canShow(MessageThread $thread, User $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        if ($thread->userIsParticipant($user)) {
            return true;
        }

        if ($user->isModerator() || $user->isAdmin()) {
            if($this->messageThreadRepository->threadContainsReportedMessage($thread)) {
                return true;
            }
        }

        return false;
    }

    private function canReply(MessageThread $thread, User $user): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        if (!$thread->userIsParticipant($user)) {
            return false;
        }

        return true;
    }
}
