<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Entry;
use App\Entity\Magazine;
use App\Entity\Post;
use App\Entity\User;
use App\Enums\ENotificationStatus;
use App\Repository\NotificationSettingsRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PostMount;

#[AsTwigComponent('notification_switch')]
class NotificationSwitch
{
    public ENotificationStatus $status = ENotificationStatus::Default;

    public Entry|Post|User|Magazine $target;

    public function __construct(
        private readonly Security $security,
        private readonly NotificationSettingsRepository $repository,
    ) {
    }

    #[PostMount]
    public function postMount(): void
    {
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $this->status = $this->repository->findOneByTarget($user, $this->target)?->getStatus() ?? ENotificationStatus::Default;
        }
    }
}
