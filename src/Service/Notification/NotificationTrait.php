<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\MagazineSubscription;
use App\Entity\User;

trait NotificationTrait
{
    /**
     * @param MagazineSubscription[] $subscriptions
     *
     * @return User[]
     */
    private function getUsersToNotify(array $subscriptions): array
    {
        return array_map(fn ($sub) => $sub->user, $subscriptions);
    }

    private function merge(array $subs, array $follows): array
    {
        return array_unique(
            array_merge(
                $subs,
                array_filter(
                    $follows,
                    function ($val) use ($subs) {
                        return !\in_array($val, $subs);
                    }
                )
            ),
            SORT_REGULAR
        );
    }
}
