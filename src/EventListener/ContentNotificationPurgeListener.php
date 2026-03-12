<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Contracts\ContentInterface;
use App\Entity\Entry;
use App\Entity\EntryComment;
use App\Entity\Post;
use App\Entity\PostComment;
use App\Entity\Report;
use App\Service\Contracts\ContentNotificationManagerInterface;
use App\Service\Notification\EntryCommentNotificationManager;
use App\Service\Notification\EntryNotificationManager;
use App\Service\Notification\PostCommentNotificationManager;
use App\Service\Notification\PostNotificationManager;
use App\Service\SwitchingServiceRegistry;
use Doctrine\Persistence\Event\LifecycleEventArgs;

readonly class ContentNotificationPurgeListener
{
    public function __construct(
        private SwitchingServiceRegistry $serviceRegistry,
    ) {
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $object = $args->getObject();
        if (!($object instanceof ContentInterface)) {
            return;
        }

        $manager = $this->serviceRegistry->getService($object, ContentNotificationManagerInterface::class);
        $manager->purgeNotifications($object);
        $manager->purgeMagazineLog($object);
    }
}
