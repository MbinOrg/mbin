<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\SettingsManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Extension\RuntimeExtensionInterface;

readonly class AdminExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private Security $security,
        private TagRepository $tagRepository,
        private SettingsManager $settingsManager,
        private UserRepository $userRepository,
    ) {
    }

    public function isTagBanned(string $tag): bool
    {
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDeniedException();
        }

        $hashtag = $this->tagRepository->findOneBy(['tag' => $tag]);
        if (null === $hashtag) {
            return false;
        }

        return $hashtag->banned;
    }

    public function doNewUsersNeedApproval(): bool
    {
        // show the signup requests page even if they are deactivated if there are any remaining
        return $this->settingsManager->getNewUsersNeedApproval() || $this->userRepository->findAllSignupRequestsPaginated()->count() > 0;
    }
}
