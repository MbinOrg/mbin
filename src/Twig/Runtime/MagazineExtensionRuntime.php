<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\Magazine;
use App\Repository\MagazineSubscriptionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\RuntimeExtensionInterface;

class MagazineExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly MagazineSubscriptionRepository $magazineSubscriptionRepository)
    {
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
}
