<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Entity\UserFilterList;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class FilterListVoter extends Voter
{
    public const string EDIT = 'edit';
    public const string DELETE = 'delete';

    protected function supports(string $attribute, $subject): bool
    {
        return $subject instanceof UserFilterList
            && \in_array(
                $attribute,
                [self::EDIT, self::DELETE],
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
            self::EDIT, self::DELETE => $this->isOwner($subject, $user),
            default => throw new \LogicException(),
        };
    }

    private function isOwner(UserFilterList $list, User $loggedInUser): bool
    {
        if ($list->user === $loggedInUser) {
            return true;
        }

        return false;
    }
}
