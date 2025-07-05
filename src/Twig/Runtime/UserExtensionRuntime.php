<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Instance;
use App\Entity\User;
use App\Repository\InstanceRepository;
use App\Repository\ReputationRepository;
use App\Service\MentionManager;
use App\Service\SettingsManager;
use App\Service\UserManager;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\RuntimeExtensionInterface;

class UserExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly MentionManager $mentionManager,
        private readonly InstanceRepository $instanceRepository,
        private readonly UserManager $userManager,
        private readonly SettingsManager $settingsManager,
        private readonly ReputationRepository $reputationRepository,
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

    public function apDomain(string $value): string
    {
        return $this->mentionManager->getDomain($value);
    }

    public function getReputationTotal(User $user): int
    {
        return $this->userManager->getReputationTotal($user);
    }

    public function getInstanceOfUser(User $user): ?Instance
    {
        return $this->instanceRepository->getInstanceOfUser($user);
    }

    public function isInstanceOfUserBanned(User $user): bool
    {
        if (null === $user->apId) {
            return false;
        }

        return $this->settingsManager->isBannedInstance($user->apProfileId);
    }

    public function getUserAttitude(User $user): float
    {
        $attitude = $this->reputationRepository->getUserAttitudes($user->getId());

        return $attitude[$user->getId()] ?? -1;
    }
}
