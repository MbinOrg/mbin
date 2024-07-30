<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Instance;
use App\Entity\User;
use App\Repository\InstanceRepository;
use App\Service\MentionManager;
use App\Service\UserManager;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\RuntimeExtensionInterface;

class UserExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly MentionManager $mentionManager,
        private readonly InstanceRepository $instanceRepository,
        private readonly UserManager $userManager
    ) {
    }

    public function isFollowed(User $followed)
    {
        if (!$this->security->getUser()) {
            return false;
        }

        return $this->security->getUser()->isFollower($followed);
    }

    public function isBlocked(User $blocked)
    {
        if (!$this->security->getUser()) {
            return false;
        }

        return $this->security->getUser()->isBlocked($blocked);
    }

    public function username(string $value, ?bool $withApPostfix = false): string
    {
        return $this->mentionManager->getUsername($value, $withApPostfix);
    }

    public function getReputationTotal(User $user): int
    {
        return $this->userManager->getReputationTotal($user);
    }

    public function getInstanceOfUser(User $user): ?Instance
    {
        return $this->instanceRepository->getInstanceOfUser($user);
    }
}
