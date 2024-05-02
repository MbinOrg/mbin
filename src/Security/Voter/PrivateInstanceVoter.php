<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Service\SettingsManager;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PrivateInstanceVoter extends Voter
{
    public function __construct(private SettingsManager $settingsManager)
    {
    }

    protected function supports(string $attribute, $subject): bool
    {
        return 'PUBLIC_ACCESS_UNLESS_PRIVATE_INSTANCE' === $attribute;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        if ($token instanceof TwoFactorTokenInterface) {
            return false;
        }

        if ($this->settingsManager->get('MBIN_PRIVATE_INSTANCE')) {
            $user = $token->getUser();

            if (!$user instanceof User) {
                return false;
            }
        }

        return true;
    }
}
