<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Instance;
use App\Entity\Magazine;
use App\Repository\InstanceRepository;
use App\Repository\MagazineSubscriptionRepository;
use App\Service\SettingsManager;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\RuntimeExtensionInterface;

class MagazineExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly InstanceRepository $instanceRepository,
        private readonly Security $security,
        private readonly MagazineSubscriptionRepository $magazineSubscriptionRepository,
        private readonly SettingsManager $settingsManager,
    ) {
    }

    public function isSubscribed(Magazine $magazine): bool
    {
        if (!$this->security->getUser()) {
            return false;
        }

        return $magazine->isSubscribed($this->security->getUser());
    }

    public function isBlocked(Magazine $magazine): bool
    {
        if (!$this->security->getUser()) {
            return false;
        }

        return $this->security->getUser()->isBlockedMagazine($magazine);
    }

    public function hasLocalSubscribers(Magazine $magazine): bool
    {
        $subscribers = $this->magazineSubscriptionRepository->findMagazineSubscribers(1, $magazine);

        return $subscribers->getNbResults() > 0;
    }

    public function getInstanceOfMagazine(Magazine $magazine): ?Instance
    {
        return $this->instanceRepository->getInstanceOfMagazine($magazine);
    }

    public function isInstanceOfMagazineBanned(Magazine $magazine): bool
    {
        if (null === $magazine->apId) {
            return false;
        }

        return $this->settingsManager->isBannedInstance($magazine->apProfileId);
    }
}
