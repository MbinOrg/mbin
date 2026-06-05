<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ModeratorDto;
use App\Entity\Instance;
use App\Entity\InstanceBlock;
use App\Entity\User;
use App\Event\InstanceBlockedEvent;
use App\Repository\InstanceBlockRepository;
use App\Repository\InstanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

readonly class InstanceManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SettingsManager $settingsManager,
        private InstanceRepository $instanceRepository,
        private InstanceBlockRepository $instanceBlockRepository,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function addModerator(ModeratorDto $dto): void
    {
        $dto->user->roles = array_unique(array_merge($dto->user->roles, ['ROLE_MODERATOR']));

        $this->entityManager->persist($dto->user);
        $this->entityManager->flush();
    }

    public function removeModerator(User $user): void
    {
        $user->roles = array_diff($user->roles, ['ROLE_MODERATOR']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /** @param string[] $bannedInstances */
    #[\Deprecated]
    public function setBannedInstances(array $bannedInstances): void
    {
        $previousBannedInstances = $this->instanceRepository->getBannedInstanceUrls();
        foreach ($bannedInstances as $instance) {
            if (!\in_array($instance, $previousBannedInstances, true)) {
                $this->banInstance($this->instanceRepository->getOrCreateInstance($instance));
            }
        }
        foreach ($previousBannedInstances as $instance) {
            if (!\in_array($instance, $bannedInstances, true)) {
                $this->unbanInstance($this->instanceRepository->getOrCreateInstance($instance));
            }
        }
    }

    public function banInstance(Instance $instance): void
    {
        if ($this->settingsManager->getUseAllowList()) {
            throw new \LogicException('Cannot ban an instance when using an allow list');
        }
        $instance->isBanned = true;
        $instance->isExplicitlyAllowed = false;

        $this->entityManager->flush();
    }

    public function unbanInstance(Instance $instance): void
    {
        if ($this->settingsManager->getUseAllowList()) {
            throw new \LogicException('Cannot unban an instance when using an allow list');
        }
        $instance->isBanned = false;

        $this->entityManager->flush();
    }

    public function allowInstanceFederation(Instance $instance): void
    {
        if (!$this->settingsManager->getUseAllowList()) {
            throw new \LogicException('Cannot allow instance federation when not using an allow list');
        }
        $instance->isExplicitlyAllowed = true;
        $instance->isBanned = false;

        $this->entityManager->flush();
    }

    public function denyInstanceFederation(Instance $instance): void
    {
        if (!$this->settingsManager->getUseAllowList()) {
            throw new \LogicException('Cannot deny instance federation when not using an allow list');
        }
        $instance->isExplicitlyAllowed = false;

        $this->entityManager->flush();
    }

    public function blockInstance(Instance $instance, User $user): void {
        $block = new InstanceBlock($user, $instance);
        $this->entityManager->persist($block);
        $this->entityManager->flush();

        $this->dispatcher->dispatch(new InstanceBlockedEvent($instance, $user, true));
    }

    public function unblockInstance(Instance $instance, User $user): void {
        $block = $this->instanceBlockRepository->findByUserAndInstance($user, $instance);
        if(null !== $block) {
            $this->entityManager->remove($block);
            $this->entityManager->flush();
        }

        $this->dispatcher->dispatch(new InstanceBlockedEvent($instance, $user, false));
    }
}
