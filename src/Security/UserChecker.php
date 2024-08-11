<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User as AppUser;
use App\Service\UserManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserManager $userManager
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof AppUser) {
            return;
        }

        if ($user->isDeleted) {
            if ($user->markedForDeletionAt > (new \DateTime('now'))) {
                $this->userManager->removeDeleteRequest($user);
            }
            else {
                throw new BadCredentialsException();
            } 
        }

        if (!$user->isVerified) {
            $resendEmailActivationUrl = $this->urlGenerator->generate('app_resend_email_activation');
            throw new CustomUserMessageAccountStatusException($this->translator->trans('your_account_is_not_active', ['%link_target%' => $resendEmailActivationUrl]));
        }

        if ($user->isBanned) {
            throw new CustomUserMessageAccountStatusException($this->translator->trans('your_account_has_been_banned'));
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof AppUser) {
            return;
        }
    }
}
